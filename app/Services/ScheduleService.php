<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\StaffSchedule;
use App\Models\StaffScheduleException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Horarios del personal: qué bloques trabaja cada profesional en una fecha y
 * si una cita choca con otra.
 *
 * Reglas de Glamora:
 *  - Horario semanal (uno o más bloques por día) + excepciones por fecha.
 *  - Excepción "off" (libre, vacaciones…) gana a todo; "custom" reemplaza el
 *    horario semanal de ese día.
 *  - Un profesional SIN horario semanal cargado no tiene restricciones: así
 *    un salón que no usa horarios no recibe avisos en la agenda.
 */
class ScheduleService
{
    /** Estados de cita que no ocupan el horario. */
    public const FREE_STATUSES = ['cancelada', 'no_show'];

    public function hasSchedule(int $personalId): bool
    {
        return StaffSchedule::withoutGlobalScopes()->where('personal_id', $personalId)->exists();
    }

    /**
     * Día de trabajo de un profesional en una fecha:
     *   configured: ¿tiene horario semanal cargado?
     *   off:        motivo si no trabaja por una excepción (null si no)
     *   special:    true si ese día tiene un horario especial
     *   blocks:     [['start' => '09:00', 'end' => '13:00'], …]
     */
    public function dayFor(int $personalId, Carbon $date): array
    {
        return $this->rangeFor($personalId, $date, $date)[$date->toDateString()];
    }

    /** dayFor() para cada fecha del rango, con dos consultas en total. */
    public function rangeFor(int $personalId, Carbon $from, Carbon $to): array
    {
        $weekly = StaffSchedule::withoutGlobalScopes()->where('personal_id', $personalId)
            ->orderBy('starts_time')->get()->groupBy('weekday');

        $exceptions = StaffScheduleException::withoutGlobalScopes()->where('personal_id', $personalId)
            ->where('date_from', '<=', $to->toDateString())
            ->where('date_to', '>=', $from->toDateString())
            ->orderBy('starts_time')->get();

        $configured = $weekly->isNotEmpty();
        $days = [];

        for ($d = $from->copy()->startOfDay(); $d->lte($to); $d->addDay()) {
            $day = $d->toDateString();
            $hits = $exceptions->filter(fn ($e) => $e->date_from->toDateString() <= $day && $e->date_to->toDateString() >= $day);
            $off = $hits->firstWhere('type', 'off');
            $custom = $hits->where('type', 'custom');

            $blocks = match (true) {
                (bool) $off           => collect(),
                $custom->isNotEmpty() => $custom,
                default               => $weekly->get($d->dayOfWeekIso, collect()),
            };

            $days[$day] = [
                'configured' => $configured,
                'off'        => $off ? ($off->reason ?: 'No trabaja') : null,
                'special'    => !$off && $custom->isNotEmpty(),
                'blocks'     => $blocks->map(fn ($b) => [
                    'start' => substr($b->starts_time, 0, 5),
                    'end'   => substr($b->ends_time, 0, 5),
                ])->values()->all(),
            ];
        }

        return $days;
    }

    /**
     * ¿La cita cabe completa dentro de un bloque de trabajo? Sin horario
     * cargado, siempre cabe (no hay restricción).
     */
    public function fits(int $personalId, Carbon $start, Carbon $end): bool
    {
        $day = $this->dayFor($personalId, $start);
        if (!$day['configured'] && !$day['off'] && !$day['special']) {
            return true;
        }

        $from = $start->format('H:i');
        $to = $end->toDateString() === $start->toDateString() ? $end->format('H:i') : '24:00';

        foreach ($day['blocks'] as $b) {
            if ($from >= $b['start'] && $to <= $b['end']) {
                return true;
            }
        }

        return false;
    }

    /** Explicación legible de por qué una cita queda fuera de horario. */
    public function outsideReason(int $personalId, Carbon $start): string
    {
        $day = $this->dayFor($personalId, $start);

        if ($day['off']) {
            return "ese día no trabaja ({$day['off']})";
        }
        if (!$day['blocks']) {
            return 'ese día no trabaja según su horario';
        }

        return 'su horario ese día es ' . collect($day['blocks'])->map(fn ($b) => "{$b['start']}–{$b['end']}")->implode(' y ');
    }

    /** Citas del mismo profesional que se superponen con el intervalo. */
    public function conflicts(int $personalId, Carbon $start, Carbon $end, ?int $ignoreId = null): Collection
    {
        return Appointment::withoutGlobalScopes()
            ->with(['client', 'service'])
            ->where('personal_id', $personalId)
            ->whereNull('deleted_at')
            ->whereNotIn('status', self::FREE_STATUSES)
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->where('starts_at', '<', $end)
            // Una cita sin fin registrado ocupa 30 minutos.
            ->whereRaw('COALESCE(ends_at, DATE_ADD(starts_at, INTERVAL 30 MINUTE)) > ?', [$start])
            ->orderBy('starts_at')
            ->get();
    }

    /** Citas que ocupan al profesional en una fecha (para mostrar huecos). */
    public function busyOn(int $personalId, Carbon $date, ?int $ignoreId = null): array
    {
        return $this->conflicts($personalId, $date->copy()->startOfDay(), $date->copy()->endOfDay(), $ignoreId)
            ->map(fn ($a) => [
                'start'  => $a->starts_at->format('H:i'),
                'end'    => ($a->ends_at ?? $a->starts_at->copy()->addMinutes(30))->format('H:i'),
                'label'  => trim(($a->client?->full_name ?? 'Cliente') . ' · ' . ($a->service?->name ?? '')),
            ])->all();
    }
}
