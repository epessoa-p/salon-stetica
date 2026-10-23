<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\CashSession;
use App\Models\Client;
use App\Models\Combo;
use App\Models\CompanySetting;
use App\Models\MembershipPlan;
use App\Models\PaymentMethod;
use App\Models\Personal;
use App\Models\Product;
use App\Models\Quote;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\ServicePackage;
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

    public function index(Request $request)
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

        $catalog = $catalog->concat($this->combos($companyId, $sellsProducts))->concat($this->packages($companyId));

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
            'canQuote'         => $user->is_super_admin || $user->hasPermissionInCompany('quotes.create', $company),
            'fromQuote'        => $request->filled('quote') ? $this->fromQuote((int) $request->input('quote'), $companyId, $catalog) : null,
        ]);
    }

    /** Combos a la venta. Con productos, se vende hasta donde alcance el stock. */
    protected function combos(int $companyId, bool $sellsProducts)
    {
        return Combo::with(['items.service', 'items.product'])->where('company_id', $companyId)->where('active', true)
            ->orderBy('sort_order')->orderBy('name')->get()
            ->filter(fn ($c) => $c->items->isNotEmpty() && ($sellsProducts || !$c->hasProducts()))
            ->map(function ($c) {
                $products = $c->items->whereNotNull('product_id');

                return [
                    'kind'   => 'combo',
                    'id'     => $c->id,
                    'name'   => $c->name,
                    'price'  => (float) $c->price,
                    'list'   => $c->listPrice(),
                    'detail' => $c->summary(),
                    // Cuántos combos alcanza el stock (null = sin productos, sin límite).
                    'stock'  => $products->isEmpty() ? null
                        : (float) $products->map(fn ($i) => floor((float) ($i->product?->current_stock ?? 0) / max(0.01, (float) $i->quantity)))->min(),
                ];
            })->values();
    }

    /** Paquetes de sesiones a la venta. */
    protected function packages(int $companyId)
    {
        return ServicePackage::with('items.service')->where('company_id', $companyId)->where('active', true)
            ->orderBy('sort_order')->orderBy('name')->get()
            ->filter(fn ($p) => $p->items->isNotEmpty())
            ->map(fn ($p) => [
                'kind'     => 'package',
                'id'       => $p->id,
                'name'     => $p->name,
                'price'    => (float) $p->price,
                'list'     => $p->listPrice(),
                'duration' => $p->validityLabel(),
                'detail'   => $p->summary(),
            ])->values();
    }

    /**
     * Carrito armado desde una cotización. Vigente: con sus precios.
     * Vencida: con los precios actuales del catálogo (y se avisa).
     */
    protected function fromQuote(int $quoteId, int $companyId, $catalog): ?array
    {
        $quote = Quote::with('items')->where('company_id', $companyId)->find($quoteId);
        if (!$quote || !$quote->canConvert()) {
            return ['error' => $quote ? 'La cotización ' . $quote->quote_number . ' ya está ' . mb_strtolower($quote->stateLabel()) . '.' : 'Cotización no encontrada.'];
        }

        $valid = $quote->isValid();
        $prices = collect($catalog)->mapWithKeys(fn ($it) => [$it['kind'] . ':' . $it['id'] => $it['price']]);
        $items = [];
        $missing = 0;

        foreach ($quote->items as $item) {
            $key = $item->catalogKey();
            if (!$key || !$prices->has($key)) {
                $missing++;
                continue;
            }
            [$kind, $id] = explode(':', $key);
            $items[] = [
                'service_id'         => $kind === 'service' ? $id : null,
                'product_id'         => $kind === 'product' ? $id : null,
                'combo_id'           => $kind === 'combo' ? $id : null,
                'service_package_id' => $kind === 'package' ? $id : null,
                'membership_plan_id' => $kind === 'membership' ? $id : null,
                'quantity'           => (float) $item->quantity,
                'unit_price'         => $valid ? (float) $item->unit_price : $prices[$key],
                'discount'           => $valid ? (float) $item->discount : 0,
                'personal_id'        => $item->personal_id,
            ];
        }

        return [
            'id'      => $quote->id,
            'number'  => $quote->quote_number,
            'valid'   => $valid,
            'message' => ($valid
                    ? 'Venta desde la cotización ' . $quote->quote_number . ': se respetan sus precios (vigente hasta el ' . $quote->valid_until->format('d/m/Y') . ').'
                    : 'La cotización ' . $quote->quote_number . ' venció el ' . $quote->valid_until->format('d/m/Y') . ': se cargaron los precios actuales.')
                . ($missing ? " {$missing} línea(s) ya no están en el catálogo y no se cargaron." : ''),
            'old'     => [
                'items'           => $items,
                'client_id'       => $quote->client_id,
                'client_name'     => $quote->client_name,
                'personal_id'     => $quote->personal_id,
                'global_discount' => $valid ? (float) $quote->global_discount : 0,
                'notes'           => $quote->notes,
            ],
        ];
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
