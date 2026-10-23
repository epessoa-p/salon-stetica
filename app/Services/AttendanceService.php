<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\CompanySetting;
use App\Models\Personal;
use App\Models\StaffSchedule;
use App\Models\User;
use DomainException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Asistencia del personal.
 *
 * Reglas de Glamora:
 *  - Cada entrada/salida es un registro; con turno partido hay varios al día.
 *  - La tardanza se calcula en la primera entrada del día contra el inicio
 *    de su primer bloque. Solo cuenta si supera la tolerancia de la empresa,
 *    y entonces se registra completa (10:20 con turno a las 10:00 = 20 min).
 *  - Quien tiene usuario marca lo suyo (self); recepción marca por otros (staff).
 *  - Las correcciones exigen motivo y quedan registradas.
 */
class AttendanceService
{
    /** Estados del día: clave => [etiqueta, clase de badge]. */
    public const STATES = [
        'working'   => ['En turno', 'bg-primary'],
        'present'   => ['Presente', 'bg-success'],
        'late'      => ['Tarde', 'bg-warning text-dark'],
        'absent'    => ['Ausente', 'bg-danger'],
        'missing'   => ['Sin marcar', 'bg-danger-subtle text-danger-emphasis'],
        'pending'   => ['Aún no entra', 'bg-light text-dark border'],
        'off'       => ['Libre', 'bg-secondary'],
        'rest'      => ['No trabaja', 'bg-light text-muted border'],
        'free'      => ['Sin horario', 'bg-light text-muted border'],
    ];

    public function __construct(protected ScheduleService $schedules)
    {
    }

    /** Registro abierto (entrada sin salida), el más reciente. */
    public function openFor(Personal $personal): ?Attendance
    {
        return Attendance::withoutGlobalScopes()
            ->where('personal_id', $personal->id)
            ->whereNull('check_out')
            ->orderByDesc('check_in')
            ->first();
    }

    public function checkIn(Personal $personal, User $by, bool $self): Attendance
    {
        return DB::transaction(function () use ($personal, $by, $self) {
            if ($open = $this->openFor($personal)) {
                throw new DomainException("{$personal->full_name} ya tiene una entrada abierta desde las "
                    . $open->check_in->format('H:i') . ($open->work_date->isToday() ? '' : ' del ' . $open->work_date->format('d/m'))
                    . '. Primero hay que marcar la salida.');
            }

            $now = now();
            [$scheduledStart, $late] = $this->lateness($personal, $now);

            return Attendance::create([
                'company_id'      => $personal->company_id,
                'personal_id'     => $personal->id,
                'work_date'       => $now->toDateString(),
                'check_in'        => $now,
                'in_source'       => $self ? 'self' : 'staff',
                'in_marked_by'    => $by->id,
                'scheduled_start' => $scheduledStart,
                'late_minutes'    => $late,
            ]);
        });
    }

    public function checkOut(Personal $personal, User $by, bool $self): Attendance
    {
        return DB::transaction(function () use ($personal, $by, $self) {
            $open = $this->openFor($personal);
            if (!$open) {
                throw new DomainException("{$personal->full_name} no tiene una entrada abierta.");
            }

            $now = now();
            $open->update([
                'check_out'      => $now,
                'out_source'     => $self ? 'self' : 'staff',
                'out_marked_by'  => $by->id,
                'worked_minutes' => max(0, (int) $open->check_in->diffInMinutes($now)),
            ]);

            return $open;
        });
    }

    /** Corrige horas de una marca. Recalcula horas trabajadas y tardanza. */
    public function correct(Attendance $attendance, Carbon $in, ?Carbon $out, string $reason, User $by): Attendance
    {
        if ($out && $out->lte($in)) {
            throw new DomainException('La salida debe ser posterior a la entrada.');
        }

        $personal = $attendance->specialist;
        $isFirst = !Attendance::withoutGlobalScopes()->where('personal_id', $attendance->personal_id)
            ->whereDate('work_date', $attendance->work_date)
            ->where('id', '!=', $attendance->id)
            ->where('check_in', '<', $in)
            ->exists();
        [$scheduledStart, $late] = $isFirst ? $this->lateness($personal, $in) : [$attendance->scheduled_start, 0];

        $attendance->update([
            'check_in'        => $in,
            'check_out'       => $out,
            'work_date'       => $in->toDateString(),
            'scheduled_start' => $scheduledStart,
            'late_minutes'    => $late,
            'worked_minutes'  => $out ? (int) $in->diffInMinutes($out) : null,
            'edited_by'       => $by->id,
            'edit_reason'     => $reason,
        ]);

        return $attendance;
    }

    /**
     * Tardanza de una entrada: [inicio de turno, minutos tarde]. Solo aplica a
     * la primera entrada del día y si supera la tolerancia.
     */
    protected function lateness(Personal $personal, Carbon $at): array
    {
        $alreadyIn = Attendance::withoutGlobalScopes()
            ->where('personal_id', $personal->id)
            ->whereDate('work_date', $at->toDateString())
            ->where('check_in', '<', $at)
            ->exists();

        $day = $this->schedules->dayFor($personal->id, $at);
        $start = $day['blocks'][0]['start'] ?? null;
        if ($alreadyIn || !$start) {
            return [$start ? $start . ':00' : null, 0];
        }

        $scheduled = Carbon::parse($at->toDateString() . ' ' . $start);
        $minutes = $scheduled->lt($at) ? (int) $scheduled->diffInMinutes($at) : 0;
        $tolerance = CompanySetting::current($personal->company_id)->attendance_tolerance_minutes;

        return [$start . ':00', $minutes > $tolerance ? $minutes : 0];
    }

    /**
     * Resumen de un día para una persona: estado, bloques del horario y marcas.
     * $records: sus registros de ese día.
     */
    public function dayStatus(array $day, $records, Carbon $date): array
    {
        $today = today();
        $late = (int) $records->sum('late_minutes');
        $worked = (int) $records->sum('worked_minutes');
        $open = $records->first(fn ($r) => $r->isOpen());
        $firstStart = $day['blocks'][0]['start'] ?? null;

        $state = match (true) {
            (bool) $open                     => 'working',
            $records->isNotEmpty()           => $late > 0 ? 'late' : 'present',
            (bool) $day['off']               => 'off',
            !$day['configured'] && !$day['special'] => 'free',
            empty($day['blocks'])            => 'rest',
            $date->lt($today)                => 'absent',
            $date->isSameDay($today) && $firstStart
                && now()->format('H:i') > $firstStart => 'missing',
            default                          => 'pending',
        };

        return [
            'state'   => $state,
            'label'   => self::STATES[$state][0] . ($state === 'late' ? " {$late} min" : '') . ($state === 'off' ? " · {$day['off']}" : ''),
            'badge'   => self::STATES[$state][1],
            'blocks'  => $day['blocks'],
            'late'    => $late,
            'worked'  => $worked,
            'open'    => $open,
            'records' => $records,
        ];
    }

    /** Tablero de hoy: una fila por persona activa. */
    public function board(int $companyId): array
    {
        $date = today();
        $people = Personal::withoutGlobalScopes()->where('company_id', $companyId)->where('active', true)
            ->whereNull('deleted_at')->orderBy('full_name')->get();
        $records = Attendance::withoutGlobalScopes()->where('company_id', $companyId)
            ->where(fn ($q) => $q->whereDate('work_date', $date)->orWhereNull('check_out'))
            ->orderBy('check_in')->get()->groupBy('personal_id');

        return $people->map(fn ($p) => ['personal' => $p] + $this->dayStatus(
            $this->schedules->dayFor($p->id, $date),
            $records->get($p->id, collect()),
            $date
        ))->all();
    }

    /**
     * Reporte por persona y día en un rango, con totales: días trabajados,
     * horas, tardanzas y ausencias (solo días pasados con turno y sin marcas).
     */
    public function report(Personal $personal, Carbon $from, Carbon $to): array
    {
        $records = Attendance::withoutGlobalScopes()->where('personal_id', $personal->id)
            ->whereBetween('work_date', [$from->toDateString(), $to->toDateString()])
            ->orderBy('check_in')->get()
            ->groupBy(fn ($r) => $r->work_date->toDateString());

        // Antes de tener horario cargado no hay ausencias: esos días cuentan como "sin horario".
        $since = StaffSchedule::withoutGlobalScopes()->where('personal_id', $personal->id)->min('created_at');
        $since = $since ? Carbon::parse($since)->toDateString() : null;

        $days = [];
        foreach ($this->schedules->rangeFor($personal->id, $from, $to) as $date => $day) {
            if ($since && $date < $since && !$day['off'] && !$day['special']) {
                $day = ['configured' => false, 'off' => null, 'special' => false, 'blocks' => []];
            }
            $days[$date] = $this->dayStatus($day, $records->get($date, collect()), Carbon::parse($date));
        }

        $collection = collect($days);

        return [
            'days'   => $days,
            'totals' => [
                'worked_days' => $collection->filter(fn ($d) => $d['records']->isNotEmpty())->count(),
                'minutes'     => $collection->sum('worked'),
                'late_days'   => $collection->where('state', 'late')->count(),
                'late_min'    => $collection->sum('late'),
                'absences'    => $collection->where('state', 'absent')->count(),
            ],
        ];
    }
}
