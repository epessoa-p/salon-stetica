<?php

namespace App\Http\Controllers\Scheduling;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Branch;
use App\Models\Client;
use App\Models\Personal;
use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class AppointmentController extends Controller
{
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
        return view('scheduling.appointments.calendar', ['noCompany' => !$this->companyId()]);
    }

    /** Feed JSON de eventos para el calendario (rango start/end de FullCalendar). */
    public function events(Request $request)
    {
        $companyId = $this->companyId();
        if (!$companyId) {
            return response()->json([]);
        }

        $query = Appointment::with(['client', 'service'])->where('company_id', $companyId);

        if ($request->filled('start')) {
            $query->where('starts_at', '>=', Carbon::parse($request->input('start')));
        }
        if ($request->filled('end')) {
            $query->where('starts_at', '<=', Carbon::parse($request->input('end')));
        }

        $events = $query->get()->map(fn (Appointment $a) => [
            'id' => $a->id,
            'title' => ($a->client?->full_name ?? 'Cliente') . ' · ' . ($a->service?->name ?? 'Servicio'),
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
            $validated = $this->validateData($request);
            $this->assertBelongsToCompany($validated, $companyId);

            $validated['company_id'] = $companyId;
            $validated['created_by'] = auth()->id();
            $validated['ends_at'] = $this->resolveEndsAt($validated);
            $validated['price'] = $this->resolvePrice($validated);
            $validated['status'] = $validated['status'] ?? 'pendiente';

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
            $validated = $this->validateData($request);
            $this->assertBelongsToCompany($validated, $appointment->company_id);

            $validated['ends_at'] = $this->resolveEndsAt($validated);
            $validated['price'] = $this->resolvePrice($validated);
            unset($validated['company_id']);

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

        $appointment->update(['status' => $validated['status']]);

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
        ]);
    }

    /** Verifica que cliente/servicio/especialista/sucursal pertenezcan a la empresa. */
    protected function assertBelongsToCompany(array $data, int $companyId): void
    {
        $checks = [
            [Client::class, $data['client_id'] ?? null],
            [Service::class, $data['service_id'] ?? null],
            [Personal::class, $data['personal_id'] ?? null],
            [Branch::class, $data['branch_id'] ?? null],
        ];

        foreach ($checks as [$model, $id]) {
            if ($id && $model::whereKey($id)->where('company_id', $companyId)->doesntExist()) {
                abort(422, 'Un elemento seleccionado no pertenece a la empresa.');
            }
        }
    }

    /** Precio: el indicado, o el del servicio, o 0 (la columna es NOT NULL). */
    protected function resolvePrice(array $data): float
    {
        if (isset($data['price']) && $data['price'] !== null && $data['price'] !== '') {
            return (float) $data['price'];
        }

        if (!empty($data['service_id'])) {
            return (float) (Service::find($data['service_id'])?->price ?? 0);
        }

        return 0.0;
    }

    /** Calcula ends_at desde la duración indicada o la del servicio (default 30 min). */
    protected function resolveEndsAt(array $data): Carbon
    {
        $start = Carbon::parse($data['starts_at']);
        $minutes = $data['duration_minutes'] ?? null;

        if (!$minutes && !empty($data['service_id'])) {
            $minutes = Service::find($data['service_id'])?->duration_minutes;
        }

        return $start->copy()->addMinutes((int) ($minutes ?: 30));
    }

    protected function authorizeAppointment(Appointment $appointment): void
    {
        if ($appointment->company_id !== $this->companyId()) {
            abort(403);
        }
    }
}
