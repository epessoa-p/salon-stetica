<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\CashSession;
use App\Models\Client;
use App\Models\CompanySetting;
use App\Models\MembershipPlan;
use App\Models\PaymentMethod;
use App\Models\Personal;
use App\Models\Product;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Services\CheckoutService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Pantalla del punto de venta y sus consultas en vivo. El cobro en sí lo
 * hace SaleController::store(), con el mismo CheckoutService.
 */
class PosController extends Controller
{
    public function __construct(protected CheckoutService $checkout)
    {
    }

    public function index()
    {
        $user = auth()->user();
        $company = $user->getCurrentCompany();
        if (!$company) {
            return view('sales.pos', ['noCompany' => true]);
        }

        $companyId = $company->id;
        $setting = CompanySetting::current($companyId);
        $sellsProducts = $company->planAllows('inventory');
        $sellsMemberships = $company->planAllows('loyalty');

        $categories = ServiceCategory::where('company_id', $companyId)->where('active', true)->orderBy('name')->get(['id', 'name']);

        $catalog = Service::where('company_id', $companyId)->where('active', true)->orderBy('name')->get()
            ->map(fn ($s) => [
                'kind'        => 'service',
                'id'          => $s->id,
                'name'        => $s->name,
                'price'       => (float) $s->price,
                'duration'    => $s->duration_minutes,
                'category_id' => $s->service_category_id,
            ]);

        if ($sellsProducts) {
            $catalog = $catalog->concat(
                Product::where('company_id', $companyId)->where('active', true)->orderBy('name')->get()
                    ->map(fn ($p) => [
                        'kind'  => 'product',
                        'id'    => $p->id,
                        'name'  => $p->name,
                        'price' => (float) $p->price,
                        'stock' => (float) $p->current_stock,
                    ])
            );
        }

        if ($sellsMemberships) {
            $catalog = $catalog->concat(
                MembershipPlan::with('sessions.service')->where('company_id', $companyId)->where('active', true)
                    ->orderBy('sort_order')->orderBy('name')->get()
                    ->map(fn ($m) => [
                        'kind'     => 'membership',
                        'id'       => $m->id,
                        'name'     => $m->name,
                        'price'    => (float) $m->price,
                        'duration' => $m->durationLabel(),
                        'detail'   => trim(((float) $m->discount_percent > 0 ? rtrim(rtrim(number_format((float) $m->discount_percent, 2), '0'), '.') . ' % en ' . mb_strtolower($m->scopeLabel()) : '')
                            . ($m->totalSessions() > 0 ? ' · ' . $m->sessions->map(fn ($s) => $s->quantity . '× ' . ($s->service?->name ?? '?'))->implode(', ') : ''), ' ·'),
                    ])
            );
        }

        $openSessions = CashSession::with('register')
            ->where('status', 'open')
            ->whereHas('register', fn ($q) => $q->where('company_id', $companyId))
            ->get();

        return view('sales.pos', [
            'noCompany'        => false,
            'setting'          => $setting,
            'catalog'          => $catalog->values(),
            'categories'       => $categories,
            'sellsProducts'    => $sellsProducts,
            'sellsMemberships' => $sellsMemberships,
            'specialists'      => Personal::where('company_id', $companyId)->where('active', true)->orderBy('full_name')->get(['id', 'full_name']),
            'defaultPrincipal' => Personal::where('company_id', $companyId)->where('user_id', $user->id)->value('id'),
            'paymentMethods'   => $this->paymentMethods($companyId),
            'branches'         => Branch::where('company_id', $companyId)->where('active', true)->orderBy('name')->get(['id', 'name']),
            'openSessions'     => $openSessions,
            'canCreateClient'  => $user->is_super_admin || $user->hasPermissionInCompany('clients.create', $company),
        ]);
    }

    /** Búsqueda de clientes por nombre, teléfono o documento (top 10). */
    public function clients(Request $request): JsonResponse
    {
        $term = trim((string) $request->input('q'));
        $companyId = auth()->user()->getCurrentCompany()?->id;

        $clients = Client::where('company_id', $companyId)
            ->where('active', true)
            ->when($term !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('full_name', 'like', "%{$term}%")
                ->orWhere('phone', 'like', "%{$term}%")
                ->orWhere('id_number', 'like', "%{$term}%")))
            ->orderBy('full_name')
            ->limit(10)
            ->get(['id', 'full_name', 'phone', 'id_number']);

        return response()->json($clients->map(fn ($c) => [
            'id'       => $c->id,
            'name'     => $c->full_name,
            'phone'    => $c->phone,
            'document' => $c->id_number,
        ]));
    }

    /** Ficha corta del cliente: membresía vigente y puntos. */
    public function client(Client $client): JsonResponse
    {
        abort_if($client->company_id !== auth()->user()->getCurrentCompany()?->id, 403);

        return response()->json($this->checkout->clientSummary($client));
    }

    /** Alta rápida de cliente desde el POS. */
    public function storeClient(Request $request): JsonResponse
    {
        $user = auth()->user();
        $company = $user->getCurrentCompany();
        abort_unless($company && ($user->is_super_admin || $user->hasPermissionInCompany('clients.create', $company)), 403);

        $data = $request->validate([
            'full_name' => ['required', 'string', 'max:255'],
            'phone'     => ['nullable', 'string', 'max:30'],
            'id_number' => ['nullable', 'string', 'max:50'],
        ], ['full_name.required' => 'Escribe el nombre del cliente.']);

        $client = Client::create($data + [
            'company_id' => $company->id,
            'active'     => true,
            'source'     => 'Punto de venta',
            'created_by' => $user->id,
        ]);

        return response()->json($this->checkout->clientSummary($client), 201);
    }

    /** Desglose autoritativo del carrito (el mismo cálculo que el cobro). */
    public function quote(Request $request): JsonResponse
    {
        $company = auth()->user()->getCurrentCompany();
        abort_unless($company, 403);

        $data = $request->validate(CheckoutService::rules($company->id, forStore: false));
        $data['items'] = $data['items'] ?? [];

        return response()->json($this->checkout->quote($data, $company));
    }

    /** Métodos de pago activos con su icono y si suman al efectivo de caja. */
    protected function paymentMethods(int $companyId): array
    {
        $methods = PaymentMethod::where('company_id', $companyId)->where('active', true)
            ->orderBy('sort_order')->orderBy('name')
            ->get(['code', 'name', 'icon', 'affects_cash', 'is_default'])
            ->map(fn ($m) => [
                'code'    => $m->code,
                'name'    => $m->name,
                'icon'    => $m->icon ?: 'bi-credit-card-2-front',
                'cash'    => (bool) $m->affects_cash,
                'default' => (bool) $m->is_default,
            ])->all();

        if ($methods) {
            return $methods;
        }

        // Sin catálogo propio: el base de PaymentMethod::FALLBACK.
        return collect(PaymentMethod::FALLBACK)->map(fn ($m, $code) => [
            'code'    => $code,
            'name'    => $m[0],
            'icon'    => $m[1],
            'cash'    => $m[2],
            'default' => $code === 'efectivo',
        ])->values()->all();
    }
}
