<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Models\CompanySetting;
use App\Models\Sale;
use App\Services\CheckoutService;
use App\Services\CommissionService;
use App\Services\CreditService;
use App\Services\LoyaltyService;
use App\Services\MembershipService;
use App\Services\PackageService;
use App\Services\ReceivableService;
use App\Services\SaleRecorder;
use App\Services\SaleReturnService;
use App\Services\StockService;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Ventas: listado, detalle, cobro y anulación. La pantalla del punto de
 * venta y sus consultas en vivo están en PosController.
 */
class SaleController extends Controller
{
    public function __construct(
        protected CheckoutService $checkout,
        protected CommissionService $commissions,
        protected MembershipService $memberships,
        protected LoyaltyService $loyalty,
        protected PackageService $packages,
        protected CreditService $credit,
        protected StockService $stock,
        protected ReceivableService $receivables,
        protected SaleRecorder $recorder,
    ) {
    }

    /** Listado de ventas. */
    public function index(Request $request)
    {
        $companyId = $this->companyId();
        if (!$companyId) {
            return view('sales.sales.index', ['sales' => null, 'noCompany' => true]);
        }

        $sales = Sale::with(['client'])
            ->where('company_id', $companyId)
            ->latest('sale_date')
            ->paginate(15);

        return view('sales.sales.index', ['sales' => $sales, 'noCompany' => false]);
    }

    public function show(Sale $sale)
    {
        $this->authorizeSale($sale);
        $sale->load([
            'items.service', 'items.product', 'items.specialist', 'items.membershipPlan', 'items.clientMembership',
            'items.combo', 'items.servicePackage', 'items.clientPackage', 'items.promotion', 'receivable.payments', 'coupon',
            'client', 'session.register', 'specialist', 'tipSettlement', 'returns.creator', 'quote',
        ]);

        return view('sales.sales.show', [
            'sale'      => $sale,
            'canReturn' => !$sale->isVoided() && $sale->items->contains(fn ($i) => $i->returnableQuantity() > 0),
        ]);
    }

    /**
     * Cobra una venta. El cálculo (descuentos, membresía, puntos, impuesto)
     * sale de CheckoutService, el mismo que muestra el POS en vivo.
     */
    public function store(Request $request)
    {
        $company = auth()->user()->getCurrentCompany();
        if (!$company) {
            return back()->withErrors(['error' => 'Debes operar dentro de una empresa.']);
        }

        $validated = $request->validate(CheckoutService::rules($company->id), [
            'items.required' => 'Agrega al menos un servicio, producto o membresía a la venta.',
        ]);

        // Preferencia: exigir una caja abierta para poder cobrar.
        if (CompanySetting::current($company->id)->require_cash_session && empty($validated['cash_session_id'])) {
            return back()->withInput()->withErrors([
                'cash_session_id' => 'Las preferencias de la empresa exigen registrar la venta en una caja abierta.',
            ]);
        }

        $quote = $this->checkout->quote($validated, $company);
        if ($quote['errors']) {
            return back()->withInput()->withErrors($quote['errors']);
        }

        try {
            $sale = DB::transaction(fn () => $this->persist($validated, $quote, $company->id));

            return redirect()->route('sales.show', $sale)->with('success', 'Venta registrada: ' . $sale->sale_number);
        } catch (DomainException $e) {
            return back()->withInput()->withErrors(['error' => $e->getMessage()]);
        } catch (\Throwable $e) {
            Log::error('Error al registrar venta', ['message' => $e->getMessage()]);

            return back()->withInput()->withErrors(['error' => 'No fue posible registrar la venta.']);
        }
    }

    /**
     * Anula una venta: revierte caja (si la sesión sigue abierta), repone el
     * stock, ajusta comisiones, cancela las membresías vendidas en ella y
     * revierte los puntos. Las sesiones de membresía usadas vuelven solas.
     */
    public function void(Sale $sale)
    {
        $this->authorizeSale($sale);

        if ($sale->isVoided()) {
            return back()->withErrors(['error' => 'La venta ya está anulada.']);
        }
        if ($sale->hasReturns()) {
            return back()->withErrors(['error' => 'La venta tiene devoluciones: ya no se puede anular. Registra una devolución por lo que falta.']);
        }

        try {
            [$reversals, $canceled, $credit] = DB::transaction(fn () => $this->voidInTransaction($sale));
        } catch (DomainException $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }

        $message = 'Venta anulada.';
        if ($reversals > 0) {
            $message .= " {$reversals} comisión(es) ya liquidada(s) quedaron en pie: se generó su reverso negativo,"
                . ' que se descontará en la próxima liquidación del profesional.';
        }
        if ($canceled > 0) {
            $message .= " Se canceló {$canceled} membresía(s) o paquete(s) vendido(s) en esta venta.";
        }
        if ($credit > 0) {
            $message .= ' El saldo a favor usado (' . number_format($credit, 2) . ') volvió al cliente.';
        }
        if ($sale->on_credit) {
            $message .= ' Se canceló su cuenta por cobrar.';
        }
        if ((float) $sale->tip > 0 && $sale->tip_settlement_id) {
            $message .= ' La propina ya se había pagado al profesional; regularízala con él.';
        }

        return back()->with('success', $message);
    }

    /** Anulación dentro de la transacción. Lanza DomainException si no se puede. */
    protected function voidInTransaction(Sale $sale): array
    {
        // Primero la deuda: si ya tiene abonos, no se anula nada.
        $this->receivables->cancelForVoidedSale($sale);
        $sale->update(['status' => 'anulada']);

        $reversals = $this->commissions->reverseForVoidedSale($sale);
        $canceled = $this->memberships->cancelForVoidedSale($sale) + $this->packages->cancelForVoidedSale($sale);
        $this->loyalty->reverseForVoidedSale($sale);
        $credit = $this->credit->reverseForVoidedSale($sale);

        foreach ($sale->items()->whereNotNull('product_id')->get() as $item) {
            $this->stock->move((int) $item->product_id, (float) $item->quantity, 'entrada', $sale->company_id, $sale->sale_number, 'Anulación venta ' . $sale->sale_number);
        }

        $session = $sale->session;
        if ($session && $session->isOpen()) {
            // Sale de caja solo lo que entró en dinero (lo pagado con saldo
            // vuelve como saldo; lo fiado nunca entró).
            if ($sale->paidInMoney() > 0) {
                $session->movements()->create([
                    'type'           => 'egreso',
                    'amount'         => $sale->paidInMoney(),
                    'concept'        => 'Anulación venta ' . $sale->sale_number,
                    'payment_method' => $sale->payment_method,
                    'reference'      => (string) $sale->id,
                    'created_by'     => auth()->id(),
                ]);
            }

            // La propina se devuelve solo si todavía no se le pagó al profesional.
            if ((float) $sale->tip > 0 && !$sale->tip_settlement_id) {
                $session->movements()->create([
                    'type'           => 'egreso',
                    'amount'         => $sale->tip,
                    'concept'        => 'Anulación propina venta ' . $sale->sale_number,
                    'category'       => 'Propinas',
                    'payment_method' => $sale->payment_method,
                    'reference'      => (string) $sale->id,
                    'created_by'     => auth()->id(),
                ]);
            }
        }

        return [$reversals, $canceled, $credit];
    }

    // ---- Helpers ----

    /** Guarda la venta ya calculada. Corre dentro de una transacción. */
    protected function persist(array $validated, array $quote, int $companyId): Sale
    {
        return $this->recorder->record($validated, $quote, $companyId);
    }

    protected function companyId(): ?int
    {
        return auth()->user()->getCurrentCompany()?->id;
    }

    protected function authorizeSale(Sale $sale): void
    {
        if ($sale->company_id !== $this->companyId()) {
            abort(403);
        }
    }
}
