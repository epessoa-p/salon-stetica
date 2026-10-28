<?php

namespace App\Http\Controllers\Api\V1;

use App\Services\ScheduleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/** Mi horario: los turnos de la semana del profesional conectado. */
class ScheduleController extends ApiController
{
    public function __construct(protected ScheduleService $schedules)
    {
    }

    public function week(Request $request): JsonResponse
    {
        $personal = $this->ownPersonal();
        if (!$personal) {
            return $this->noPersonal();
        }

        $base = $request->filled('date') ? Carbon::parse($request->input('date')) : today();
        $from = $base->copy()->startOfWeek(Carbon::MONDAY);
        $to = $from->copy()->addDays(6);
        $days = $this->schedules->rangeFor($personal->id, $from, $to);

        return response()->json([
            'from'       => $from->toDateString(),
            'to'         => $to->toDateString(),
            'configured' => (bool) (collect($days)->first()['configured'] ?? false),
            'days'       => collect($days)->map(fn ($d, $date) => [
                'date'    => $date,
                'off'     => $d['off'],
                'special' => $d['special'],
                'blocks'  => $d['blocks'],
                'minutes' => collect($d['blocks'])->sum(fn ($b) => Carbon::parse($b['start'])->diffInMinutes(Carbon::parse($b['end']))),
            ])->values(),
        ]);
    }
}
