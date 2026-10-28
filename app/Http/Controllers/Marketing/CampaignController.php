<?php

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\Coupon;
use App\Models\Service;
use App\Services\CampaignService;
use App\Services\MessageService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Campañas: se elige un segmento de clientes y un mensaje; la lista de
 * destinatarios queda fija y se envía uno a uno por WhatsApp con avance.
 */
class CampaignController extends Controller
{
    public function __construct(protected CampaignService $campaigns, protected MessageService $messages)
    {
    }

    public function index()
    {
        $companyId = $this->companyId();
        if (!$companyId) {
            return view('marketing.campaigns.index', ['noCompany' => true]);
        }

        $list = Campaign::with('coupon')->withCount(['recipients', 'recipients as sent_count' => fn ($q) => $q->whereNotNull('sent_at')])
            ->where('company_id', $companyId)->latest('id')->paginate(20);

        return view('marketing.campaigns.index', ['noCompany' => false, 'campaigns' => $list]);
    }

    /** Formulario con vista previa del segmento (cuántos y quiénes). */
    public function create(Request $request)
    {
        $companyId = $this->companyId();
        abort_unless($companyId, 403);
        $filters = $this->filters($request);
        $services = Service::where('company_id', $companyId)->where('active', true)->orderBy('name')->get(['id', 'name']);
        $template = $this->messages->defaultFor($companyId, 'campaign');

        return view('marketing.campaigns.form', [
            'filters'  => $filters,
            'services' => $services,
            'coupons'  => Coupon::where('company_id', $companyId)->where('active', true)->whereNull('client_id')->orderBy('code')->get(),
            'templates' => $this->messages->templatesFor($companyId, 'campaign'),
            'body'     => $request->input('body', $template?->body),
            'name'     => $request->input('name'),
            'couponId' => $request->input('coupon_id'),
            'count'    => $this->campaigns->query($companyId, $filters)->count(),
            'sample'   => $this->campaigns->query($companyId, $filters)->limit(8)->get(['clients.id', 'clients.full_name', 'clients.phone']),
            'segment'  => $this->campaigns->describe($filters, $services->pluck('name', 'id')->all()),
        ]);
    }

    public function store(Request $request)
    {
        $companyId = $this->companyId();
        abort_unless($companyId, 403);
        $data = $request->validate([
            'name'      => ['required', 'string', 'max:120'],
            'body'      => ['required', 'string', 'max:1000'],
            'coupon_id' => ['nullable', Rule::exists('coupons', 'id')->where('company_id', $companyId)],
        ], ['name.required' => 'Ponle un nombre a la campaña.', 'body.required' => 'Escribe el mensaje.']);
        $filters = $this->filters($request);

        if ($this->campaigns->query($companyId, $filters)->doesntExist()) {
            return back()->withInput()->withErrors(['error' => 'El segmento no tiene clientes con teléfono: ajusta los filtros.']);
        }

        $campaign = $this->campaigns->create($companyId, $data + ['filters' => $filters]);

        return redirect()->route('marketing.campaigns.show', $campaign)->with('success', 'Campaña creada con ' . $campaign->recipients()->count() . ' destinatario(s). Envíales el mensaje uno a uno.');
    }

    public function show(Request $request, Campaign $campaign)
    {
        $this->authorizeCampaign($campaign);
        $pending = $request->input('filter') === 'pending';

        $recipients = $campaign->recipients()->with('client')
            ->when($pending, fn ($q) => $q->whereNull('sent_at'))
            ->orderByRaw('sent_at IS NOT NULL, id')
            ->paginate(50)->withQueryString();

        $total = $campaign->recipients()->count();
        $sent = $campaign->recipients()->whereNotNull('sent_at')->count();
        $services = Service::where('company_id', $campaign->company_id)->pluck('name', 'id')->all();

        return view('marketing.campaigns.show', [
            'campaign'   => $campaign->load('coupon'),
            'recipients' => $recipients,
            'total'      => $total,
            'sent'       => $sent,
            'pending'    => $pending,
            'segment'    => $this->campaigns->describe($campaign->filters ?? [], $services),
            'preview'    => $this->messages->render($campaign->body, [
                'nombre' => 'María', 'cliente' => 'María López', 'salon' => auth()->user()->getCurrentCompany()?->name,
                'cupon' => $campaign->coupon?->code, 'descuento' => $campaign->coupon?->valueLabel(),
            ]),
        ]);
    }

    public function complete(Campaign $campaign)
    {
        $this->authorizeCampaign($campaign);
        $campaign->update(['status' => $campaign->status === 'done' ? 'active' : 'done']);

        return back()->with('success', $campaign->status === 'done' ? 'Campaña cerrada.' : 'Campaña reabierta.');
    }

    protected function filters(Request $request): array
    {
        $f = $request->validate([
            'inactive_days'  => ['nullable', 'integer', 'min:1', 'max:3650'],
            'service_id'     => ['nullable', 'integer'],
            'service_days'   => ['nullable', 'integer', 'min:1', 'max:3650'],
            'membership'     => ['nullable', 'in:with,without'],
            'birthday_month' => ['nullable', 'integer', 'between:1,12'],
            'min_spent'      => ['nullable', 'numeric', 'min:0'],
        ]);

        return array_filter($f, fn ($v) => $v !== null && $v !== '');
    }

    protected function authorizeCampaign(Campaign $campaign): void
    {
        abort_if($campaign->company_id !== $this->companyId(), 403);
    }

    protected function companyId(): ?int
    {
        return auth()->user()->getCurrentCompany()?->id;
    }
}
