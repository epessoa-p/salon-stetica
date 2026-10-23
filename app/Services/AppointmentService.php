<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Branch;
use App\Models\Client;
use App\Models\Personal;
use App\Models\Service;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Reglas para agendar una cita, compartidas por la web y la app móvil:
 * pertenencia a la empresa, duración, precio y validación contra el horario
 * del profesional (solape = rechazo; fuera de horario = aviso salvo que se
 * fuerce con "agendar de todos modos").
 */
class AppointmentService
{
    public function __construct(protected ScheduleService $schedules)
    {
    }

    /**
     * Completa y valida los datos de una cita. Devuelve el arreglo listo para
     * guardar (con ends_at, price, status y outside_schedule).
     *
     * @throws ValidationException
     */
    public function prepare(array $data, int $companyId, bool $forceOutside = false, ?Appointment $existing = null): array
    {
        $this->assertBelongsToCompany($data, $companyId);

        // Si la hora llega con zona (p. ej. "…T14:00Z" desde un celular), se pasa
        // a la hora local del salón: la base guarda la hora de pared, sin zona.
        $data['starts_at'] = Carbon::parse($data['starts_at'])
            ->setTimezone(config('app.timezone'))
            ->format('Y-m-d H:i:s');
        $data['ends_at'] = $this->resolveEndsAt($data);
        $data['price'] = $this->resolvePrice($data);
        $data['status'] = $data['status'] ?? $existing?->status ?? 'pendiente';
        $data['outside_schedule'] = $this->checkSchedule($data, $forceOutside, $existing?->id);
        unset($data['force_outside'], $data['duration_minutes']);

        return $data;
    }

    /**
     * Solape con otra cita del mismo profesional → se rechaza siempre.
     * Fuera de su horario → se rechaza con aviso (clave `outside_schedule`)
     * salvo que se fuerce. Devuelve true si la cita queda fuera de horario.
     */
    public function checkSchedule(array $data, bool $force, ?int $ignoreId = null): bool
    {
        $personalId = $data['personal_id'] ?? null;
        if (!$personalId || in_array($data['status'] ?? 'pendiente', ScheduleService::FREE_STATUSES, true)) {
            return false;
        }

        $start = Carbon::parse($data['starts_at']);
        $end = $data['ends_at'];
        $name = Personal::find($personalId)?->full_name ?? 'El profesional';

        if ($clash = $this->schedules->conflicts($personalId, $start, $end, $ignoreId)->first()) {
            throw ValidationException::withMessages(['starts_at' => $this->clashMessage($name, $clash)]);
        }

        if ($this->schedules->fits($personalId, $start, $end)) {
            return false;
        }

        if (!$force) {
            throw ValidationException::withMessages([
                'outside_schedule' => "La cita queda fuera del horario de {$name}: " . $this->schedules->outsideReason($personalId, $start)
                    . '. Si igual quieres agendarla, marca «Agendar de todos modos».',
            ]);
        }

        return true;
    }

    /**
     * Cambiar el estado: reactivar una cita cancelada vuelve a ocupar el
     * horario, así que no debe chocar con otra.
     *
     * @throws ValidationException
     */
    public function changeStatus(Appointment $appointment, string $status): void
    {
        $freed = in_array($appointment->status, ScheduleService::FREE_STATUSES, true);
        $occupies = !in_array($status, ScheduleService::FREE_STATUSES, true);

        if ($freed && $occupies && $appointment->personal_id) {
            $clash = $this->schedules->conflicts($appointment->personal_id, $appointment->starts_at,
                $appointment->ends_at ?? $appointment->starts_at->copy()->addMinutes(30), $appointment->id)->first();
            if ($clash) {
                throw ValidationException::withMessages(['status' => $this->clashMessage($appointment->specialist?->full_name, $clash)]);
            }
        }

        $appointment->update(['status' => $status]);
    }

    public function clashMessage(?string $name, Appointment $clash): string
    {
        $end = $clash->ends_at ?? $clash->starts_at->copy()->addMinutes(30);

        return ($name ?? 'El profesional') . ' ya tiene una cita de ' . $clash->starts_at->format('H:i') . ' a ' . $end->format('H:i')
            . ' (' . trim(($clash->client?->full_name ?? 'Cliente') . ' · ' . ($clash->service?->name ?? ''), ' ·') . ')'
            . '. Elige otro horario u otro profesional.';
    }

    /** Cliente, servicio, profesional y sucursal deben ser de la empresa. */
    protected function assertBelongsToCompany(array $data, int $companyId): void
    {
        $checks = [
            'client_id'   => Client::class,
            'service_id'  => Service::class,
            'personal_id' => Personal::class,
            'branch_id'   => Branch::class,
        ];

        foreach ($checks as $field => $model) {
            $id = $data[$field] ?? null;
            if ($id && $model::withoutGlobalScopes()->whereKey($id)->where('company_id', $companyId)->doesntExist()) {
                throw ValidationException::withMessages([$field => 'El elemento seleccionado no pertenece a la empresa.']);
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
            return (float) (Service::withoutGlobalScopes()->find($data['service_id'])?->price ?? 0);
        }

        return 0.0;
    }

    /** Fin: la duración indicada, o la del servicio, o 30 minutos. */
    protected function resolveEndsAt(array $data): Carbon
    {
        $start = Carbon::parse($data['starts_at']);
        $minutes = $data['duration_minutes'] ?? null;

        if (!$minutes && !empty($data['service_id'])) {
            $minutes = Service::withoutGlobalScopes()->find($data['service_id'])?->duration_minutes;
        }

        return $start->copy()->addMinutes((int) ($minutes ?: 30));
    }
}
