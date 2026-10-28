<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Appointment;
use App\Models\Client;
use App\Models\Coupon;
use App\Models\MessageLog;
use App\Services\MessageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Recordatorios de citas y cumpleaños desde la app. El mensaje lo arma el
 * servidor (mismas plantillas que la web) y la app abre WhatsApp con él.
 */
class MessagingController extends ApiController
{
    public function __construct(protected MessageService $messages)
    {
    }

    /** Citas pendientes o confirmadas de un día (por defecto, mañana), con su último aviso. */
    public function reminders(Request $request): JsonResponse
    {
        if ($r = $this->planMissing('scheduling')) {
            return $r;
        }
        if (!$this->can('reminders.view')) {
            return $this->forbidden('No tienes permiso para los recordatorios.');
        }

        $day = $request->filled('date') ? Carbon::parse($request->input('date')) : today()->addDay();
        $company = $this->company();

        $appointments = Appointment::with(['client', 'service', 'specialist'])
            ->where('company_id', $company->id)->whereDate('starts_at', $day)
            ->whereIn('status', ['pendiente', 'confirmada'])->orderBy('starts_at')->get();
        $logs = MessageLog::with('sender')->where('type', 'reminder')->whereIn('appointment_id', $appointments->pluck('id'))
            ->latest('sent_at')->get()->groupBy('appointment_id');
        $own = $this->ownPersonal();

        return response()->json([
            'date'         => $day->toDateString(),
            'templates'    => $this->templates($company->id, 'reminder'),
            'appointments' => $appointments->map(fn ($a) => AgendaController::appointment($a, $own) + [
                'reminded_at' => ($l = $logs->get($a->id)?->first())?->sent_at?->toIso8601String(),
                'reminded_by' => $l?->sender?->name,
            ])->values(),
        ]);
    }

    /** Clientes que cumplen años hoy, en 7 días o este mes. */
    public function birthdays(Request $request): JsonResponse
    {
        if ($r = $this->planMissing('marketing')) {
            return $r;
        }
        if (!$this->can('marketing.view')) {
            return $this->forbidden('No tienes permiso para Marketing.');
        }

        $company = $this->company();
        $range = $request->input('range', 'today');
        $today = today();
        $keys = collect(match ($range) {
            'month' => range(1, $today->daysInMonth),
            'week'  => range(0, 6),
            default => [0],
        })->map(fn ($n) => ($range === 'month' ? $today->copy()->day($n) : $today->copy()->addDays($n))->format('m-d'));

        $clients = Client::where('company_id', $company->id)->where('active', true)->whereNotNull('birthdate')->get()
            ->filter(fn ($c) => $keys->contains($c->birthdate->format('m-d')))
            ->sortBy(fn ($c) => $keys->search($c->birthdate->format('m-d')))->values();
        $sent = MessageLog::where('type', 'birthday')->whereIn('client_id', $clients->pluck('id'))->whereYear('sent_at', $today->year)
            ->get()->keyBy('client_id');

        return response()->json([
            'range'     => $range,
            'templates' => $this->templates($company->id, 'birthday'),
            'coupons'   => Coupon::where('company_id', $company->id)->where('active', true)->whereNull('client_id')->orderBy('code')->get()
                ->map(fn ($c) => ['id' => $c->id, 'code' => $c->code, 'value' => $c->valueLabel()])->values(),
            'clients'   => $clients->map(fn ($c) => ClientController::client($c) + [
                'birthday' => $c->birthdate->copy()->year($today->year)->toDateString(),
                'age'      => $c->birthdate->year > 1900 ? $today->year - $c->birthdate->year : null,
                'is_today' => $c->birthdate->format('m-d') === $today->format('m-d'),
                'greeted_at' => $sent->get($c->id)?->sent_at?->toIso8601String(),
            ])->values(),
        ]);
    }

    /** Arma el mensaje, lo registra y devuelve el enlace de WhatsApp para abrirlo. */
    public function whatsapp(Request $request): JsonResponse
    {
        $data = $request->validate([
            'client_id'      => ['required', 'integer'],
            'type'           => ['required', 'in:reminder,birthday,general'],
            'appointment_id' => ['nullable', 'integer'],
            'template_id'    => ['nullable', 'integer'],
            'coupon_id'      => ['nullable', 'integer'],
        ]);

        $permission = $data['type'] === 'reminder' ? 'reminders.view' : 'marketing.send';
        if (!$this->can($permission)) {
            return $this->forbidden('No tienes permiso para enviar este mensaje.');
        }

        $company = $this->company();
        $client = Client::where('company_id', $company->id)->find($data['client_id']);
        if (!$client) {
            return $this->fail(404, 'not_found', 'Cliente no encontrado.');
        }
        if (!$client->phone) {
            return $this->fail(422, 'no_phone', 'El cliente no tiene teléfono.');
        }

        return response()->json($this->messages->compose($company, $client, $data['type'],
            $data['appointment_id'] ?? null, null, $data['template_id'] ?? null, $data['coupon_id'] ?? null));
    }

    protected function templates(int $companyId, string $type): array
    {
        return $this->messages->templatesFor($companyId, $type)
            ->map(fn ($t) => ['id' => $t->id, 'name' => $t->name, 'is_default' => (bool) $t->is_default])->values()->all();
    }
}
