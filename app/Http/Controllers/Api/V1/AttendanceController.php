<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Attendance;
use App\Services\AttendanceService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/** Asistencia propia: estado de hoy, marcar entrada/salida y resumen del periodo. */
class AttendanceController extends ApiController
{
    public function __construct(protected AttendanceService $attendance)
    {
    }

    public function show(Request $request): JsonResponse
    {
        $personal = $this->ownPersonal();
        if (!$personal) {
            return $this->noPersonal();
        }

        $from = $request->filled('from') ? Carbon::parse($request->input('from')) : today()->startOfMonth();
        $to = $request->filled('to') ? Carbon::parse($request->input('to')) : today();
        if ($from->diffInDays($to) > 92) {
            $from = $to->copy()->subDays(92);
        }

        $report = $this->attendance->report($personal, $from->startOfDay(), $to->startOfDay());
        $open = $this->attendance->openFor($personal);
        $today = $report['days'][today()->toDateString()] ?? null;

        return response()->json([
            'personal' => ['id' => $personal->id, 'full_name' => $personal->full_name],
            'open'     => $open ? ['id' => $open->id, 'check_in' => $open->check_in->toIso8601String(), 'work_date' => $open->work_date->toDateString()] : null,
            'today'    => $today ? self::day(today()->toDateString(), $today) : null,
            'totals'   => $report['totals'] + ['hours_label' => Attendance::duration($report['totals']['minutes'])],
            // Del más reciente al más antiguo, sin días futuros vacíos.
            'days'     => collect($report['days'])
                ->filter(fn ($d, $date) => $date <= today()->toDateString())
                ->map(fn ($d, $date) => self::day($date, $d))
                ->reverse()->values(),
        ]);
    }

    /** Marca entrada o salida. Sin `action`, alterna según haya entrada abierta. */
    public function mark(Request $request): JsonResponse
    {
        $personal = $this->ownPersonal();
        if (!$personal) {
            return $this->noPersonal();
        }

        $action = $request->validate(['action' => ['nullable', Rule::in(['in', 'out'])]])['action']
            ?? ($this->attendance->openFor($personal) ? 'out' : 'in');

        try {
            $record = $action === 'in'
                ? $this->attendance->checkIn($personal, auth()->user(), true)
                : $this->attendance->checkOut($personal, auth()->user(), true);
        } catch (DomainException $e) {
            return $this->fail(422, 'attendance_rejected', $e->getMessage());
        }

        $message = $action === 'in'
            ? 'Entrada registrada a las ' . $record->check_in->format('H:i') . '.' . ($record->late_minutes > 0 ? " Llegaste {$record->late_minutes} min tarde." : '')
            : 'Salida registrada a las ' . $record->check_out->format('H:i') . ' · ' . Attendance::duration($record->worked_minutes) . ' trabajados.';

        return response()->json([
            'action'       => $action,
            'message'      => $message,
            'late_minutes' => $record->late_minutes,
            'check_in'     => $record->check_in->toIso8601String(),
            'check_out'    => $record->check_out?->toIso8601String(),
        ]);
    }

    protected static function day(string $date, array $d): array
    {
        return [
            'date'    => $date,
            'state'   => $d['state'],
            'label'   => $d['label'],
            'blocks'  => $d['blocks'],
            'late'    => $d['late'],
            'worked'  => $d['worked'],
            'records' => $d['records']->map(fn ($r) => [
                'check_in'  => $r->check_in->format('H:i'),
                'check_out' => $r->check_out?->format('H:i'),
                'edited'    => (bool) $r->edited_by,
            ])->values(),
        ];
    }
}
