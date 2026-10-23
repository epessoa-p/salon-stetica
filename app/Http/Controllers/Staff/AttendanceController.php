<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\CompanySetting;
use App\Models\Personal;
use App\Services\AttendanceService;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/**
 * Asistencia: tablero del día, reporte por persona, marcas propias y por
 * recepción, y correcciones con motivo.
 */
class AttendanceController extends Controller
{
    public function __construct(protected AttendanceService $attendance)
    {
    }

    /** Tablero de hoy + reporte por persona y rango. */
    public function index(Request $request)
    {
        $companyId = $this->companyId();
        if (!$companyId) {
            return view('staff.attendance.index', ['noCompany' => true]);
        }

        [$from, $to] = $this->period($request);
        $people = Personal::where('company_id', $companyId)->where('active', true)->orderBy('full_name')->get(['id', 'full_name', 'user_id']);
        $selected = $request->integer('personal_id') ? $people->firstWhere('id', $request->integer('personal_id')) : null;

        return view('staff.attendance.index', [
            'noCompany' => false,
            'board'     => $this->attendance->board($companyId),
            'people'    => $people,
            'selected'  => $selected,
            'report'    => $selected ? $this->attendance->report($selected, $from, $to) : null,
            'from'      => $from,
            'to'        => $to,
            'tolerance' => CompanySetting::current($companyId)->attendance_tolerance_minutes,
            'canMark'   => $this->can('attendance.mark'),
            'canEdit'   => $this->can('attendance.edit'),
            'own'       => $this->ownPersonal(),
        ]);
    }

    /** Vista del propio profesional: marcar y ver su mes. No requiere permiso. */
    public function mine(Request $request)
    {
        $personal = $this->ownPersonal();
        if (!$personal) {
            return view('staff.attendance.mine', ['personal' => null]);
        }

        [$from, $to] = $this->period($request);

        return view('staff.attendance.mine', [
            'personal' => $personal,
            'open'     => $this->attendance->openFor($personal),
            'report'   => $this->attendance->report($personal, $from, $to),
            'from'     => $from,
            'to'       => $to,
        ]);
    }

    /** Marca propia: entrada o salida según corresponda. */
    public function self(Request $request)
    {
        $personal = $this->ownPersonal();
        abort_unless($personal, 403, 'Tu usuario no está vinculado a una ficha de personal.');

        return $this->doMark($personal, $request->input('action'), true);
    }

    /** Marca de recepción por otra persona. */
    public function mark(Request $request)
    {
        $data = $request->validate([
            'personal_id' => ['required', Rule::exists('personals', 'id')->where('company_id', $this->companyId())],
            'action'      => ['required', Rule::in(['in', 'out'])],
        ]);

        return $this->doMark(Personal::findOrFail($data['personal_id']), $data['action'], false);
    }

    /** Corrección de una marca con motivo obligatorio. */
    public function update(Request $request, Attendance $attendance)
    {
        abort_if($attendance->company_id !== $this->companyId(), 403);

        $data = $request->validate([
            'check_in'  => ['required', 'date'],
            'check_out' => ['nullable', 'date'],
            'reason'    => ['required', 'string', 'max:255'],
        ], ['reason.required' => 'Indica el motivo de la corrección: queda registrado.']);

        try {
            $this->attendance->correct(
                $attendance,
                Carbon::parse($data['check_in']),
                !empty($data['check_out']) ? Carbon::parse($data['check_out']) : null,
                $data['reason'],
                auth()->user(),
            );
        } catch (DomainException $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }

        return back()->with('success', 'Marca corregida.');
    }

    // ---- Helpers ----

    protected function doMark(Personal $personal, ?string $action, bool $self)
    {
        $action = in_array($action, ['in', 'out'], true)
            ? $action
            : ($this->attendance->openFor($personal) ? 'out' : 'in');

        try {
            $record = $action === 'in'
                ? $this->attendance->checkIn($personal, auth()->user(), $self)
                : $this->attendance->checkOut($personal, auth()->user(), $self);
        } catch (DomainException $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }

        $who = $self ? '' : " de {$personal->full_name}";
        $message = $action === 'in'
            ? "Entrada{$who} registrada a las " . $record->check_in->format('H:i') . '.'
                . ($record->late_minutes > 0 ? " Llegó {$record->late_minutes} min tarde." : '')
            : "Salida{$who} registrada a las " . $record->check_out->format('H:i') . ' · ' . Attendance::duration($record->worked_minutes) . ' trabajados.';

        return back()->with($action === 'in' && $record->late_minutes > 0 ? 'warning' : 'success', $message);
    }

    protected function ownPersonal(): ?Personal
    {
        $companyId = $this->companyId();

        return $companyId
            ? Personal::where('company_id', $companyId)->where('user_id', auth()->id())->first()
            : null;
    }

    /** Periodo elegido; por defecto el mes en curso. */
    protected function period(Request $request): array
    {
        $from = $request->filled('from') ? Carbon::parse($request->input('from')) : today()->startOfMonth();
        $to = $request->filled('to') ? Carbon::parse($request->input('to')) : today()->endOfMonth();
        if ($to->lt($from)) {
            [$from, $to] = [$to, $from];
        }
        // Un rango acotado mantiene el reporte legible y rápido.
        if ($from->diffInDays($to) > 92) {
            $to = $from->copy()->addDays(92);
        }

        return [$from->startOfDay(), $to->startOfDay()];
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
