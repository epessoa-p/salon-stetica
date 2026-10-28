<?php

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Coupon;
use App\Models\MessageLog;
use App\Services\MessageService;
use Illuminate\Http\Request;

/** Cumpleaños de clientes (hoy, esta semana o este mes) con saludo por WhatsApp. */
class BirthdayController extends Controller
{
    public function __construct(protected MessageService $messages)
    {
    }

    public function index(Request $request)
    {
        $company = auth()->user()->getCurrentCompany();
        if (!$company) {
            return view('marketing.birthdays.index', ['noCompany' => true]);
        }

        $range = $request->input('range', 'week');
        $today = today();
        // Días (mes-día) del rango, comparando sin el año.
        $days = match ($range) {
            'today' => [$today->copy()],
            'month' => collect(range(1, $today->daysInMonth))->map(fn ($d) => $today->copy()->day($d))->all(),
            default => collect(range(0, 6))->map(fn ($i) => $today->copy()->addDays($i))->all(),
        };
        $keys = collect($days)->map(fn ($d) => $d->format('m-d'));

        $clients = Client::where('company_id', $company->id)->where('active', true)->whereNotNull('birthdate')->get()
            ->filter(fn ($c) => $keys->contains($c->birthdate->format('m-d')))
            ->sortBy(fn ($c) => $keys->search($c->birthdate->format('m-d')))
            ->values();

        $sentThisYear = MessageLog::where('type', 'birthday')->whereIn('client_id', $clients->pluck('id'))
            ->whereYear('sent_at', $today->year)->get()->keyBy('client_id');

        $templates = $this->messages->templatesFor($company->id, 'birthday');

        return view('marketing.birthdays.index', [
            'noCompany'  => false,
            'range'      => $range,
            'clients'    => $clients,
            'sent'       => $sentThisYear,
            'templates'  => $templates,
            'templateId' => (int) $request->input('template_id', $templates->first()?->id),
            'coupons'    => $company->planAllows('marketing')
                ? Coupon::where('company_id', $company->id)->where('active', true)->whereNull('client_id')->orderBy('code')->get() : collect(),
            'couponId'   => $request->input('coupon_id'),
        ]);
    }
}
