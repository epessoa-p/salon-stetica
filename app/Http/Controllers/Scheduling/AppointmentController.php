<?php

namespace App\Http\Controllers\Scheduling;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Branch;
use App\Models\Client;
use App\Models\Personal;
use App\Models\Service;
use App\Services\AppointmentService;
use App\Services\ScheduleService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class AppointmentController extends Controller
{
    public function __construct(
        protected ScheduleService $schedules,
        protected AppointmentService $appointments,
    ) {
    }

    /** Lista de citas con filtros por estado, fecha y cliente. */
    public function index(Request $request)
    {
        $companyId = $this->companyId();
        if (!$companyId) {
            return view('scheduling.appointments.index', ['appointments' => null, 'noCompany' => true, 'filters' => []]);
        }

        $status = $request->input('status');
        $date = $request->input('date');
        $search = trim((string) $request->input('q'));

        $query = Appointment::with(['client', 'service', 'specialist'])
            ->where('company_id', $companyId)
            ->orderBy('starts_at', 'desc');

        if ($status && array_key_exists($status, Appointment::STATUSES)) {
            $query->where('status', $status);
        }
        if ($date) {
            $query->whereDate('starts_at', $date);
        }
        if ($search !== '') {
            $query->whereHas('client', fn ($q) => $q->where('full_name', 'like', "%{$search}%"));
        }

        return view('scheduling.appointments.index', [
            'appointments' => $query->paginate(15)->withQueryString(),
            'noCompany' => false,
            'filters' => compact('status', 'date', 'search'),
        ]);
    }

    /** Vista de calendario. */
    public function calendar()
    {
        $companyId = $this->companyId();

        return view('scheduling.appointments.calendar', [
            'noCompany'   => !$companyId,
            'specialists' => $companyId
                ? Personal::where('company_id', $companyId)->where('active', true)->orderBy('full_name')->get(['id', 'full_name'])
                : collect(),
        ]);
    }

    /** Feed JSON de eventos para el calendario (rango start/end de FullCalendar). */
    public function events(Request $request)
    {
        $companyId = $this->companyId();
        if (!$companyId) {
            return response()->json([]);
        }

        $query = Appointment::with(['client', 'service', 'specialist'])->where('company_id', $companyId);

        if ($request->filled('personal_id')) {
            $query->where('personal_id', $request->integer('personal_id'));
        }
        if ($request->filled('start')) {
            $query->where('starts_at', '>=', Carbon::parse($request->input('start')));
        }
        if ($request->filled('end')) {
            $query->where('starts_at', '<=', Carbon::parse($request->input('end')));
        }

        $events = $query->get()->map(fn (Appointment $a) => [
            'id' => $a->id,
            'title' => ($a->outside_schedule ? '⚠ ' : '') . ($a->client?->full_name ?? 'Cliente') . ' · ' . ($a->service?->name ?? 'Servicio')
                . ($a->specialist ? ' · ' . $a->specialist->full_name : ''),
            'start' => $a->starts_at->toIso8601String(),
            'end' => optional($a->ends_at)->toIso8601String(),
            'color' => $a->statusColor(),
            'url' => route('appointments.edit', $a),
        ]);

        return response()->json($events);
    }

    public function create(Request $request)
    {
        if (!$this->companyId()) {
            return redirect()->route('appointments.index');
        }

        return view('scheduling.appointments.create', array_merge($this->formData(), [
            'appointment' => null,
            'prefillDate' => $request->input('date'),
        ]));
    }

    public function store(Request $request)
    {
        $companyId = $this->companyId();
        if (!$companyId) {
            return back()->withErrors(['error' => 'Debes operar dentro de una empresa para crear citas.']);
        }

        try {
            $validated = $this->appointments->prepare($this->validateData($request), $companyId, $request->boolean('force_outside'));
            $validated['company_id'] = $companyId;
            $validated['created_by'] = auth()->id();

            Appointment::create($validated);

            return redirect()->route('appointments.index')->with('success', 'Cita registrada exitosamente.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('Error al crear cita', ['message' => $e->getMessage()]);
            return back()->withInput()->withErrors(['error' => 'No fue posible registrar la cita.']);
        }
    }

    public function edit(Appointment $appointment)
    {
        $this->authorizeAppointment($appointment);

        return view('scheduling.appointments.edit', array_merge($this->formData(), [
            'appointment' => $appointment,
            'prefillDate' => null,
        ]));
    }

    public function update(Request $request, Appointment $appointment)
    {
        $this->authorizeAppointment($appointment);

        try {
            $validated = $this->appointments->prepare($this->validateData($request), $appointment->company_id, $request->boolean('force_outside'), $appointment);

            $appointment->update($validated);

            return redirect()->route('appointments.index')->with('success', 'Cita actualizada exitosamente.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('Error al actualizar cita', ['appointment_id' => $appointment->id, 'message' => $e->getMessage()]);
            return back()->withInput()->withErrors(['error' => 'No fue posible actualizar la cita.']);
        }
    }

    /** Cambio rápido de estado. */
    public function updateStatus(Request $request, Appointment $appointment)
    {
        $this->authorizeAppointment($appointment);

        $validated = $request->validate([
            'status' => ['required', 'in:' . implode(',', array_keys(Appointment::STATUSES))],
        ]);

        // Reactivar una cita cancelada vuelve a ocupar el horario: el servicio verifica que no choque.
        try {
            $this->appointments->changeStatus($appointment, $validated['status']);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return back()->with('success', 'Estado de la cita actualizado.');
    }

    public function destroy(Appointment $appointment)
    {
        $this->authorizeAppointment($appointment);
        $appointment->delete();

        return redirect()->route('appointments.index')->with('success', 'Cita eliminada.');
    }

    // ---- Helpers ----

    protected function companyId(): ?int
    {
        return auth()->user()->getCurrentCompany()?->id;
    }

    protected function formData(): array
    {
        $companyId = $this->companyId();

        return [
            'clients' => Client::where('company_id', $companyId)->where('active', true)->orderBy('full_name')->get(),
            'services' => Service::where('company_id', $companyId)->where('active', true)->orderBy('name')->get(),
            'specialists' => Personal::where('company_id', $companyId)->where('active', true)->orderBy('full_name')->get(),
            'branches' => Branch::where('company_id', $companyId)->where('active', true)->orderBy('name')->get(),
            'statuses' => Appointment::STATUSES,
        ];
    }

    protected function validateData(Request $request): array
    {
        return $request->validate([
            'client_id'   => ['required', 'exists:clients,id'],
            'service_id'  => ['nullable', 'exists:services,id'],
            'personal_id' => ['nullable', 'exists:personals,id'],
            'branch_id'   => ['nullable', 'exists:branches,id'],
            'starts_at'   => ['required', 'date'],
            'duration_minutes' => ['nullable', 'integer', 'min:0', 'max:1440'],
            'price'       => ['nullable', 'numeric', 'min:0'],
            'status'      => ['nullable', 'in:' . implode(',', array_keys(Appointment::STATUSES))],
            'notes'       => ['nullable', 'string'],
            'force_outside' => ['nullable', 'boolean'],
        ]);
    }

    /** Horario y citas de un profesional en una fecha (para el formulario de cita). */
    public function availability(Request $request)
    {
        $companyId = $this->companyId();
        $personal = Personal::where('company_id', $companyId)->find($request->integer('personal_id'));
        if (!$personal || !$request->filled('date')) {
            return response()->json(null);
        }

        $date = Carbon::parse($request->input('date'));

        return response()->json($this->schedules->dayFor($personal->id, $date) + [
            'name' => $personal->full_name,
            'busy' => $this->schedules->busyOn($personal->id, $date, $request->integer('ignore') ?: null),
        ]);
    }

    /**
     * Horario de un profesional como eventos de fondo para el calendario:
     * bloques de trabajo y días libres.
     */
    public function scheduleEvents(Request $request)
    {
        $personal = Personal::where('company_id', $this->companyId())->find($request->integer('personal_id'));
        if (!$personal || !$request->filled(['start', 'end'])) {
            return response()->json([]);
        }

        $from = Carbon::parse($request->input('start'))->startOfDay();
        $to = Carbon::parse($request->input('end'))->subSecond()->startOfDay();
        $events = [];

        foreach ($this->schedules->rangeFor($personal->id, $from, $to) as $date => $day) {
            if ($day['off']) {
                $events[] = ['start' => $date, 'allDay' => true, 'display' => 'background', 'color' => '#9ca3af', 'title' => $day['off']];
                continue;
            }
            foreach ($day['blocks'] as $b) {
                $events[] = ['start' => "{$date}T{$b['start']}", 'end' => "{$date}T{$b['end']}", 'display' => 'background',
                             'color' => $day['special'] ? '#f59e0b' : '#10b981'];
            }
        }

        return response()->json($events);
    }

    protected function authorizeAppointment(Appointment $appointment): void
    {
        if ($appointment->company_id !== $this->companyId()) {
            abort(403);
        }
    }
}
