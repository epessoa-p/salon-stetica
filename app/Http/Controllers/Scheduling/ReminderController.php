<?php

namespace App\Http\Controllers\Scheduling;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\MessageLog;
use App\Services\MessageService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Recordatorios de citas: la lista del día (por defecto, mañana) con un botón
 * de WhatsApp por cita. Queda registrado quién avisó y a qué hora.
 */
class ReminderController extends Controller
{
    public function __construct(protected MessageService $messages)
    {
    }

    public function index(Request $request)
    {
        $company = auth()->user()->getCurrentCompany();
        if (!$company) {
            return view('scheduling.reminders.index', ['noCompany' => true]);
        }

        $day = $request->filled('date') ? Carbon::parse($request->input('date'))->startOfDay() : today()->addDay();
        $filter = $request->input('filter', 'all');

        $appointments = Appointment::with(['client', 'service', 'specialist'])
            ->where('company_id', $company->id)
            ->whereDate('starts_at', $day)
            ->whereIn('status', ['pendiente', 'confirmada'])
            ->orderBy('starts_at')
            ->get();

        $logs = MessageLog::with('sender')->where('type', 'reminder')
            ->whereIn('appointment_id', $appointments->pluck('id'))
            ->latest('sent_at')->get()->groupBy('appointment_id');

        if ($filter === 'pending') {
            $appointments = $appointments->filter(fn ($a) => !$logs->has($a->id))->values();
        }

        $templates = $this->messages->templatesFor($company->id, 'reminder');

        return view('scheduling.reminders.index', [
            'noCompany'    => false,
            'day'          => $day,
            'filter'       => $filter,
            'appointments' => $appointments,
            'logs'         => $logs,
            'templates'    => $templates,
            'templateId'   => (int) $request->input('template_id', $templates->first()?->id),
        ]);
    }
}
