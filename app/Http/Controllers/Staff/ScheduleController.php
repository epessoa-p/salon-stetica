<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Personal;
use App\Models\StaffSchedule;
use App\Models\StaffScheduleException;
use App\Services\ScheduleService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Horario semanal y excepciones (libres, vacaciones, horarios especiales) del personal. */
class ScheduleController extends Controller
{
    public function __construct(protected ScheduleService $schedules)
    {
    }

    public function index()
    {
        $companyId = $this->companyId();
        if (!$companyId) {
            return view('staff.schedules.index', ['noCompany' => true]);
        }

        $people = Personal::with(['schedules', 'cargo'])
            ->where('company_id', $companyId)->where('active', true)
            ->orderBy('full_name')->get();

        $upcoming = StaffScheduleException::with('specialist')
            ->where('company_id', $companyId)
            ->where('date_to', '>=', today()->toDateString())
            ->orderBy('date_from')->limit(12)->get();

        // Quién trabaja hoy, para el resumen superior.
        $today = $people->mapWithKeys(fn ($p) => [$p->id => $this->schedules->dayFor($p->id, today())]);

        return view('staff.schedules.index', [
            'noCompany' => false,
            'people'    => $people,
            'upcoming'  => $upcoming,
            'today'     => $today,
            'canEdit'   => $this->can('schedules.edit'),
        ]);
    }

    public function edit(Personal $personal)
    {
        $this->authorizePersonal($personal);
        $personal->load(['schedules', 'cargo']);

        $exceptions = StaffScheduleException::where('personal_id', $personal->id)
            ->where('date_to', '>=', today()->subDays(30)->toDateString())
            ->orderBy('date_from')->get();

        return view('staff.schedules.edit', [
            'personal'   => $personal,
            'weekly'     => $personal->schedules->groupBy('weekday'),
            'exceptions' => $exceptions,
            'canEdit'    => $this->can('schedules.edit'),
        ]);
    }

    /** Reemplaza el horario semanal completo. */
    public function update(Request $request, Personal $personal)
    {
        $this->authorizePersonal($personal);

        $request->validate([
            'days'                   => ['nullable', 'array'],
            'days.*.on'              => ['nullable', 'boolean'],
            'days.*.blocks'          => ['nullable', 'array'],
            'days.*.blocks.*.start'  => ['nullable', 'date_format:H:i'],
            'days.*.blocks.*.end'    => ['nullable', 'date_format:H:i'],
        ]);

        $rows = [];
        foreach (StaffSchedule::WEEKDAYS as $weekday => $dayName) {
            $day = $request->input("days.$weekday", []);
            if (empty($day['on'])) {
                continue;
            }

            $blocks = collect($day['blocks'] ?? [])
                ->filter(fn ($b) => !empty($b['start']) && !empty($b['end']))
                ->sortBy('start')->values();

            if ($blocks->isEmpty()) {
                throw ValidationException::withMessages(["days.$weekday" => "{$dayName}: indica al menos un bloque de horario o desmárcalo."]);
            }

            foreach ($blocks as $i => $b) {
                if ($b['end'] <= $b['start']) {
                    throw ValidationException::withMessages(["days.$weekday" => "{$dayName}: la hora de salida ({$b['end']}) debe ser posterior a la de entrada ({$b['start']})."]);
                }
                if ($i > 0 && $b['start'] < $blocks[$i - 1]['end']) {
                    throw ValidationException::withMessages(["days.$weekday" => "{$dayName}: los bloques {$blocks[$i - 1]['start']}–{$blocks[$i - 1]['end']} y {$b['start']}–{$b['end']} se superponen."]);
                }
                $rows[] = ['weekday' => $weekday, 'starts_time' => $b['start'] . ':00', 'ends_time' => $b['end'] . ':00'];
            }
        }

        DB::transaction(function () use ($personal, $rows) {
            // Fecha desde la que tiene horario: se conserva al editar, porque
            // las ausencias solo se cuentan desde entonces.
            $since = StaffSchedule::where('personal_id', $personal->id)->min('created_at');

            StaffSchedule::where('personal_id', $personal->id)->delete();
            foreach ($rows as $row) {
                $schedule = StaffSchedule::create($row + ['company_id' => $personal->company_id, 'personal_id' => $personal->id]);
                if ($since) {
                    $schedule->forceFill(['created_at' => $since])->saveQuietly();
                }
            }
        });

        return redirect()->route('schedules.edit', $personal)->with('success', $rows
            ? 'Horario guardado. La agenda ya lo usa para avisar citas fuera de horario.'
            : 'Horario vaciado: la agenda no aplicará restricciones de horario a esta persona.');
    }

    /** Agrega un día libre / vacaciones (rango) o un horario especial (un día). */
    public function storeException(Request $request, Personal $personal)
    {
        $this->authorizePersonal($personal);

        $data = $request->validate([
            'type'        => ['required', Rule::in(array_keys(StaffScheduleException::TYPES))],
            'date_from'   => ['required', 'date'],
            'date_to'     => ['nullable', 'date', 'after_or_equal:date_from'],
            'starts_time' => ['nullable', 'required_if:type,custom', 'date_format:H:i'],
            'ends_time'   => ['nullable', 'required_if:type,custom', 'date_format:H:i', 'after:starts_time'],
            'reason'      => ['nullable', 'string', 'max:150'],
        ], [
            'date_to.after_or_equal'   => 'La fecha "hasta" no puede ser anterior a "desde".',
            'starts_time.required_if'  => 'Indica la hora de entrada del horario especial.',
            'ends_time.required_if'    => 'Indica la hora de salida del horario especial.',
            'ends_time.after'          => 'La salida debe ser posterior a la entrada.',
        ]);

        $custom = $data['type'] === 'custom';
        $exception = StaffScheduleException::create([
            'company_id'  => $personal->company_id,
            'personal_id' => $personal->id,
            'type'        => $data['type'],
            'date_from'   => $data['date_from'],
            // El horario especial es de un solo día.
            'date_to'     => $custom ? $data['date_from'] : ($data['date_to'] ?? $data['date_from']),
            'starts_time' => $custom ? $data['starts_time'] . ':00' : null,
            'ends_time'   => $custom ? $data['ends_time'] . ':00' : null,
            'reason'      => $data['reason'] ?? null,
            'created_by'  => auth()->id(),
        ]);

        // Avisa de las citas ya agendadas que quedan afectadas.
        $affected = Appointment::where('personal_id', $personal->id)
            ->whereNotIn('status', ScheduleService::FREE_STATUSES)
            ->whereBetween('starts_at', [$exception->date_from->copy()->startOfDay(), $exception->date_to->copy()->endOfDay()])
            ->get()
            ->filter(fn ($a) => !$this->schedules->fits($personal->id, $a->starts_at, $a->ends_at ?? $a->starts_at->copy()->addMinutes(30)));

        $message = 'Excepción registrada.';
        if ($affected->isNotEmpty()) {
            $message .= " Atención: {$personal->full_name} tiene {$affected->count()} cita(s) que ahora quedan fuera de su horario ("
                . $affected->take(3)->map(fn ($a) => $a->starts_at->format('d/m H:i'))->implode(', ')
                . ($affected->count() > 3 ? '…' : '') . '). Reprográmalas o asígnalas a otra persona.';
        }

        return redirect()->route('schedules.edit', $personal)->with($affected->isNotEmpty() ? 'warning' : 'success', $message);
    }

    public function destroyException(Personal $personal, StaffScheduleException $exception)
    {
        $this->authorizePersonal($personal);
        abort_if($exception->personal_id !== $personal->id, 404);

        $exception->delete();

        return redirect()->route('schedules.edit', $personal)->with('success', 'Excepción eliminada: ese periodo vuelve al horario semanal.');
    }

    // ---- Helpers ----

    protected function authorizePersonal(Personal $personal): void
    {
        abort_if($personal->company_id !== $this->companyId(), 403);
    }

    protected function can(string $permission): bool
    {
        $user = auth()->user();
        $company = $user->getCurrentCompany();

        return $user->is_super_admin || ($company && $user->hasPermissionInCompany($permission, $company));
    }

    protected function companyId(): ?int
    {
        return auth()->user()->getCurrentCompany()?->id;
    }
}
