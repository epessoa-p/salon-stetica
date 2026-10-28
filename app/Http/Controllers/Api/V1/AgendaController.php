<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Appointment;
use App\Models\Personal;
use App\Models\Service;
use App\Services\AppointmentService;
use App\Services\ScheduleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Agenda en la app. Un profesional ve y gestiona sus citas sin permisos
 * extra; con appointments.view ve las de todo el equipo. Agendar usa las
 * mismas reglas que la web (AppointmentService).
 */
class AgendaController extends ApiController
{
    public function __construct(
        protected ScheduleService $schedules,
        protected AppointmentService $appointments,
    ) {
    }

    /** Citas de un rango (por defecto, hoy). */
    public function index(Request $request): JsonResponse
    {
        if ($r = $this->planMissing('scheduling')) {
            return $r;
        }

        $data = $request->validate([
            'date'        => ['nullable', 'date'],
            'days'        => ['nullable', 'integer', 'min:1', 'max:31'],
            'personal_id' => ['nullable', 'integer'],
        ]);

        $own = $this->ownPersonal();
        $all = $this->can('appointments.view');
        if (!$all && !$own) {
            return $this->noPersonal();
        }

        // Sin permiso general, solo lo propio aunque pida otro profesional.
        $personalId = $all ? ($data['personal_id'] ?? null) : $own->id;
        $from = Carbon::parse($data['date'] ?? today())->startOfDay();
        $to = $from->copy()->addDays(($data['days'] ?? 1) - 1)->endOfDay();

        $items = Appointment::with(['client', 'service', 'specialist'])
            ->where('company_id', $this->company()->id)
            ->when($personalId, fn ($q) => $q->where('personal_id', $personalId))
            ->whereBetween('starts_at', [$from, $to])
            ->orderBy('starts_at')
            ->get();

        return response()->json([
            'from'         => $from->toDateString(),
            'to'           => $to->toDateString(),
            'personal_id'  => $personalId,
            'schedule'     => $personalId ? $this->schedules->rangeFor($personalId, $from, $to) : null,
            'appointments' => $items->map(fn ($a) => self::appointment($a, $own))->values(),
        ]);
    }

    /** Servicios y profesionales para agendar. */
    public function options(): JsonResponse
    {
        if ($r = $this->planMissing('scheduling')) {
            return $r;
        }

        $companyId = $this->company()->id;

        return response()->json([
            'services' => Service::where('company_id', $companyId)->where('active', true)->orderBy('name')->get()
                ->map(fn ($s) => ['id' => $s->id, 'name' => $s->name, 'price' => (float) $s->price, 'duration' => $s->duration_minutes]),
            'specialists' => Personal::where('company_id', $companyId)->where('active', true)->orderBy('full_name')->get()
                ->map(fn ($p) => ['id' => $p->id, 'name' => $p->full_name]),
            'statuses' => collect(Appointment::STATUSES)->map(fn ($s, $k) => ['key' => $k, 'label' => $s[0], 'color' => $s[1]])->values(),
        ]);
    }

    /** Horario y citas de un profesional en una fecha. */
    public function availability(Request $request): JsonResponse
    {
        $data = $request->validate(['personal_id' => ['required', 'integer'], 'date' => ['required', 'date']]);
        $personal = Personal::where('company_id', $this->company()->id)->find($data['personal_id']);
        if (!$personal) {
            return $this->fail(404, 'not_found', 'Profesional no encontrado.');
        }

        $date = Carbon::parse($data['date']);

        return response()->json($this->schedules->dayFor($personal->id, $date) + [
            'name' => $personal->full_name,
            'busy' => $this->schedules->busyOn($personal->id, $date, $request->integer('ignore') ?: null),
        ]);
    }

    /**
     * Agenda una cita. Si queda fuera del horario responde 422 con
     * `errors.outside_schedule`: la app confirma y reenvía con force_outside.
     */
    public function store(Request $request): JsonResponse
    {
        if ($r = $this->planMissing('scheduling')) {
            return $r;
        }
        if (!$this->can('appointments.create')) {
            return $this->forbidden('No tienes permiso para agendar citas.');
        }

        $data = $request->validate([
            'client_id'        => ['required', 'integer'],
            'service_id'       => ['nullable', 'integer'],
            'personal_id'      => ['nullable', 'integer'],
            'starts_at'        => ['required', 'date'],
            'duration_minutes' => ['nullable', 'integer', 'min:5', 'max:1440'],
            'notes'            => ['nullable', 'string', 'max:1000'],
            'force_outside'    => ['nullable', 'boolean'],
        ]);

        $companyId = $this->company()->id;
        $prepared = $this->appointments->prepare($data, $companyId, $request->boolean('force_outside'));
        $appointment = Appointment::create($prepared + ['company_id' => $companyId, 'created_by' => auth()->id()]);

        return response()->json(self::appointment($appointment->load(['client', 'service', 'specialist']), $this->ownPersonal()), 201);
    }

    /** Cambia el estado: lo propio siempre; lo ajeno con appointments.edit. */
    public function status(Request $request, Appointment $appointment): JsonResponse
    {
        if ($appointment->company_id !== $this->company()->id) {
            return $this->fail(404, 'not_found', 'Cita no encontrada.');
        }

        $own = $this->ownPersonal();
        $isOwn = $own && (int) $appointment->personal_id === (int) $own->id;
        if (!$isOwn && !$this->can('appointments.edit')) {
            return $this->forbidden('Solo puedes cambiar el estado de tus propias citas.');
        }

        $data = $request->validate(['status' => ['required', Rule::in(array_keys(Appointment::STATUSES))]]);
        $this->appointments->changeStatus($appointment, $data['status']);

        return response()->json(self::appointment($appointment->fresh(['client', 'service', 'specialist']), $own));
    }

    /**
     * Reprograma una cita (fecha, hora, duración y profesional). Mismas
     * reglas que la web: choque = 422; fuera de horario = 422 con
     * `errors.outside_schedule` y se reenvía con force_outside.
     */
    public function reschedule(Request $request, Appointment $appointment): JsonResponse
    {
        if ($appointment->company_id !== $this->company()->id) {
            return $this->fail(404, 'not_found', 'Cita no encontrada.');
        }

        $own = $this->ownPersonal();
        $isOwn = $own && (int) $appointment->personal_id === (int) $own->id;
        if (!$isOwn && !$this->can('appointments.edit')) {
            return $this->forbidden('Solo puedes reprogramar tus propias citas.');
        }
        if (!in_array($appointment->status, ['pendiente', 'confirmada'], true)) {
            return $this->fail(422, 'not_reschedulable', 'Solo se reprograman citas pendientes o confirmadas.');
        }

        $data = $request->validate([
            'starts_at'        => ['required', 'date'],
            'duration_minutes' => ['nullable', 'integer', 'min:5', 'max:1440'],
            'personal_id'      => ['nullable', 'integer'],
            'force_outside'    => ['nullable', 'boolean'],
        ]);

        // Cambiar de profesional es reasignar la cita: requiere permiso de edición.
        $personalId = $request->has('personal_id') ? $data['personal_id'] : $appointment->personal_id;
        if ((int) $personalId !== (int) $appointment->personal_id && !$this->can('appointments.edit')) {
            return $this->forbidden('No tienes permiso para asignar la cita a otro profesional.');
        }

        $currentMinutes = $appointment->ends_at ? (int) $appointment->starts_at->diffInMinutes($appointment->ends_at) : 30;
        $prepared = $this->appointments->prepare([
            'client_id'        => $appointment->client_id,
            'service_id'       => $appointment->service_id,
            'personal_id'      => $personalId,
            'starts_at'        => $data['starts_at'],
            'duration_minutes' => $data['duration_minutes'] ?? $currentMinutes,
            'price'            => $appointment->price,
            'status'           => $appointment->status,
        ], $appointment->company_id, $request->boolean('force_outside'), $appointment);

        $appointment->update($prepared);

        return response()->json(self::appointment($appointment->fresh(['client', 'service', 'specialist']), $own));
    }

    public static function appointment(Appointment $a, ?Personal $own = null): array
    {
        return [
            'id'               => $a->id,
            'starts_at'        => $a->starts_at->toIso8601String(),
            'ends_at'          => ($a->ends_at ?? $a->starts_at->copy()->addMinutes(30))->toIso8601String(),
            'status'           => $a->status,
            'status_label'     => $a->statusLabel(),
            'status_color'     => $a->statusColor(),
            'price'            => (float) $a->price,
            'notes'            => $a->notes,
            'outside_schedule' => (bool) $a->outside_schedule,
            'is_mine'          => $own && (int) $a->personal_id === (int) $own->id,
            'client'           => $a->client ? ['id' => $a->client->id, 'name' => $a->client->full_name, 'phone' => $a->client->phone] : null,
            'service'          => $a->service ? ['id' => $a->service->id, 'name' => $a->service->name] : null,
            'personal'         => $a->specialist ? ['id' => $a->specialist->id, 'name' => $a->specialist->full_name] : null,
        ];
    }
}
