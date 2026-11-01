<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Sales\PosController;
use App\Models\CashSession;
use App\Models\Client;
use App\Models\CompanySetting;
use App\Models\PaymentMethod;
use App\Models\Personal;
use App\Models\Sale;
use App\Models\ServiceCategory;
use App\Services\CheckoutService;
use App\Services\SaleRecorder;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Cobro desde la app y «Mis ventas». El cálculo es el de CheckoutService y el
 * guardado el de SaleRecorder: los mismos que el punto de venta web.
 */
class SalesController extends ApiController
{
    public function __construct(protected CheckoutService $checkout, protected SaleRecorder $recorder)
    {
    }

    /** Catálogo y datos para armar una venta. */
    public function options(): JsonResponse
    {
        if ($r = $this->guard('sales.create')) {
            return $r;
        }

        $company = $this->company();
        $pos = app(PosController::class);
        $setting = CompanySetting::current($company->id);

        return response()->json([
            'currency'          => $setting->currency_symbol,
            // Stock del almacén de la sucursal del usuario.
            'catalog'           => $pos->catalog($company, app(\App\Services\StockService::class)->warehouseFor($company->id, app(\App\Services\BranchResolver::class)->defaultFor($company->id, auth()->id()))),
            'categories'        => ServiceCategory::where('company_id', $company->id)->where('active', true)->orderBy('name')->get(['id', 'name']),
            'specialists'       => Personal::where('company_id', $company->id)->where('active', true)->orderBy('full_name')->get(['id', 'full_name'])
                ->map(fn ($p) => ['id' => $p->id, 'name' => $p->full_name])->values(),
            'default_principal' => $this->ownPersonal()?->id,
            'payment_methods'   => $pos->paymentMethods($company->id),
            'cash_sessions'     => $this->openSessions(),
            'require_cash'      => (bool) $setting->require_cash_session,
            'features'          => [
                'coupons'        => $company->planAllows('marketing'),
                'loyalty'        => $company->planAllows('loyalty'),
                'clients_create' => $company->planAllows('crm') && $this->can('clients.create'),
            ],
        ]);
    }

    /** Búsqueda de clientes para la venta (no exige ver la cartera de clientes). */
    public function clients(Request $request): JsonResponse
    {
        if ($r = $this->guard('sales.create')) {
            return $r;
        }

        $term = trim((string) $request->input('q'));
        $clients = Client::where('company_id', $this->company()->id)
            ->where('active', true)
            ->when($term !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('full_name', 'like', "%{$term}%")
                ->orWhere('phone', 'like', "%{$term}%")
                ->orWhere('id_number', 'like', "%{$term}%")))
            ->orderBy('full_name')
            ->limit(15)
            ->get(['id', 'full_name', 'phone', 'id_number']);

        return response()->json($clients->map(fn ($c) => ['id' => $c->id, 'name' => $c->full_name, 'phone' => $c->phone, 'document' => $c->id_number])->values());
    }

    /** Desglose en vivo del carrito (sin cobrar). */
    public function quote(Request $request): JsonResponse
    {
        if ($r = $this->guard('sales.create')) {
            return $r;
        }

        $data = $request->validate(CheckoutService::rules($this->company()->id, forStore: false));
        $data['items'] = $data['items'] ?? [];

        return response()->json($this->checkout->quote($data, $this->company()));
    }

    /** Cobra la venta. */
    public function store(Request $request): JsonResponse
    {
        if ($r = $this->guard('sales.create')) {
            return $r;
        }

        $company = $this->company();
        $validated = $request->validate(CheckoutService::rules($company->id), [
            'items.required' => 'Agrega al menos un servicio, producto o membresía a la venta.',
        ]);

        if (CompanySetting::current($company->id)->require_cash_session && empty($validated['cash_session_id'])) {
            return $this->fail(422, 'cash_required', 'Las preferencias del salón exigen registrar la venta en una caja abierta.');
        }

        $quote = $this->checkout->quote($validated, $company);
        if ($quote['errors']) {
            return $this->fail(422, 'checkout_invalid', $quote['errors'][0], ['errors' => $quote['errors']]);
        }

        try {
            $sale = DB::transaction(fn () => $this->recorder->record($validated, $quote, $company->id));
        } catch (DomainException $e) {
            return $this->fail(422, 'checkout_invalid', $e->getMessage());
        } catch (\Throwable $e) {
            Log::error('Error al registrar venta (app)', ['message' => $e->getMessage()]);

            return $this->fail(500, 'sale_failed', 'No fue posible registrar la venta.');
        }

        return response()->json([
            'message' => 'Venta registrada: ' . $sale->sale_number,
            'sale'    => $this->detail($sale->fresh()),
        ], 201);
    }

    /**
     * Ventas de un día. Con `sales.view` ve las del salón (o solo las suyas con
     * mine=1); si solo puede cobrar, ve las que registró él.
     */
    public function index(Request $request): JsonResponse
    {
        $company = $this->company();
        if ($r = $this->planMissing('sales')) {
            return $r;
        }
        $all = $this->can('sales.view');
        if (!$all && !$this->can('sales.create')) {
            return $this->forbidden('No tienes permiso para ver ventas.');
        }

        $day = $request->date('date') ?? today();
        $mine = !$all || $request->boolean('mine');

        $sales = Sale::with('client')
            ->where('company_id', $company->id)
            ->whereDate('sale_date', $day)
            ->when($mine, fn ($q) => $q->where('created_by', auth()->id()))
            ->latest('sale_date')
            ->get();
        $valid = $sales->reject->isVoided();

        return response()->json([
            'date'     => $day->toDateString(),
            'currency' => CompanySetting::current($company->id)->currency_symbol,
            'scope'    => $mine ? 'mine' : 'all',
            'can_all'  => $all,
            'totals'   => [
                'count' => $valid->count(),
                'total' => round((float) $valid->sum('total'), 2),
                'tips'  => round((float) $valid->sum('tip'), 2),
                'by_method' => $valid->groupBy('payment_method')
                    ->map(fn ($g, $code) => ['method' => PaymentMethod::label($code, $company->id), 'amount' => round((float) $g->sum('total'), 2), 'count' => $g->count()])
                    ->values(),
            ],
            'sales' => $sales->map(fn ($s) => $this->row($s))->values(),
        ]);
    }

    public function show(Sale $sale): JsonResponse
    {
        if ($sale->company_id !== $this->company()->id) {
            return $this->fail(404, 'not_found', 'Venta no encontrada.');
        }
        if (!$this->can('sales.view') && !($this->can('sales.create') && $sale->created_by === auth()->id())) {
            return $this->forbidden('No tienes permiso para ver esta venta.');
        }

        return response()->json($this->detail($sale));
    }

    // ---- Helpers ----

    /** Módulo de ventas en el plan y el permiso indicado. */
    protected function guard(string $permission): ?JsonResponse
    {
        if ($r = $this->planMissing('sales')) {
            return $r;
        }

        return $this->can($permission) ? null : $this->forbidden('No tienes permiso para cobrar.');
    }

    protected function openSessions(): array
    {
        return CashSession::with('register')->where('status', 'open')
            ->whereHas('register', fn ($q) => $q->where('company_id', $this->company()->id))
            ->get()
            ->map(fn ($s) => ['id' => $s->id, 'name' => $s->register?->name])
            ->values()->all();
    }

    protected function row(Sale $sale): array
    {
        return [
            'id'      => $sale->id,
            'number'  => $sale->sale_number,
            'client'  => $sale->clientDisplay(),
            'total'   => (float) $sale->total,
            'tip'     => (float) $sale->tip,
            'method'  => PaymentMethod::label($sale->payment_method, $sale->company_id),
            'status'  => $sale->isVoided() ? 'anulada' : ($sale->on_credit ? 'credito' : 'completada'),
            'at'      => $sale->sale_date?->toIso8601String(),
        ];
    }

    protected function detail(Sale $sale): array
    {
        $sale->load(['items.specialist', 'client', 'session.register', 'coupon']);

        return $this->row($sale) + [
            'subtotal'  => (float) $sale->subtotal,
            'discounts' => round((float) $sale->discount + (float) $sale->membership_discount + (float) $sale->package_discount
                + (float) $sale->promotion_discount + (float) $sale->coupon_discount + (float) $sale->loyalty_discount, 2),
            'tax'       => (float) $sale->tax,
            'credit_applied' => (float) $sale->credit_applied,
            'coupon'    => $sale->coupon?->code,
            'points_redeemed' => (int) $sale->loyalty_points_redeemed,
            'cash'      => $sale->session?->register?->name,
            'notes'     => $sale->notes,
            'currency'  => CompanySetting::current($sale->company_id)->currency_symbol,
            'items'     => $sale->items->map(fn ($i) => [
                'description' => $i->description,
                'quantity'    => (float) $i->quantity,
                'unit_price'  => (float) $i->unit_price,
                'total'       => (float) $i->total,
                'specialist'  => $i->specialist?->full_name,
                'session'     => (bool) $i->uses_session || (float) $i->package_discount > 0 && (float) $i->total == 0.0,
            ])->values(),
        ];
    }
}
