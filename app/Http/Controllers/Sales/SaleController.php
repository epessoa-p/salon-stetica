<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Models\CashSession;
use App\Models\CompanySetting;
use App\Models\MembershipPlan;
use App\Models\Product;
use App\Models\Quote;
use App\Models\Sale;
use App\Models\ServicePackage;
use App\Services\CheckoutService;
use App\Services\CommissionService;
use App\Services\CreditService;
use App\Services\LoyaltyService;
use App\Services\MembershipService;
use App\Services\PackageService;
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
            'items.combo', 'items.servicePackage', 'items.clientPackage',
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

        [$reversals, $canceled, $credit] = DB::transaction(function () use ($sale) {
            $sale->update(['status' => 'anulada']);

            $reversals = $this->commissions->reverseForVoidedSale($sale);
            $canceled = $this->memberships->cancelForVoidedSale($sale) + $this->packages->cancelForVoidedSale($sale);
            $this->loyalty->reverseForVoidedSale($sale);
            $credit = $this->credit->reverseForVoidedSale($sale);

            foreach ($sale->items()->whereNotNull('product_id')->get() as $item) {
                $this->stock->move((int) $item->product_id, (float) $item->quantity, 'entrada', $sale->company_id, $sale->sale_number, 'Anulación venta ' . $sale->sale_number);
            }

            // Sale de caja solo lo que entró en dinero (lo pagado con saldo vuelve como saldo).
            $session = $sale->session;
            if ($session && $session->isOpen() && $sale->paidInMoney() > 0) {
                $session->movements()->create([
                    'type'           => 'egreso',
                    'amount'         => $sale->paidInMoney(),
                    'concept'        => 'Anulación venta ' . $sale->sale_number,
                    'payment_method' => $sale->payment_method,
                    'reference'      => (string) $sale->id,
                    'created_by'     => auth()->id(),
                ]);

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
        });

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
        if ((float) $sale->tip > 0 && $sale->tip_settlement_id) {
            $message .= ' La propina ya se había pagado al profesional; regularízala con él.';
        }

        return back()->with('success', $message);
    }

    // ---- Helpers ----

    /** Guarda la venta ya calculada. Corre dentro de una transacción. */
    protected function persist(array $validated, array $quote, int $companyId): Sale
    {
        $principalId = $validated['personal_id'] ?? null;

        // Sesión de caja (opcional): debe estar abierta y ser de la empresa.
        $session = null;
        if (!empty($validated['cash_session_id'])) {
            $session = CashSession::where('id', $validated['cash_session_id'])
                ->where('status', 'open')
                ->whereHas('register', fn ($q) => $q->where('company_id', $companyId))
                ->first();
        }

        $number = $this->nextSaleNumber($companyId);

        $sale = Sale::create([
            'company_id'              => $companyId,
            'branch_id'               => $validated['branch_id'] ?? null,
            'client_id'               => $validated['client_id'] ?? null,
            'personal_id'             => $principalId,
            'cash_session_id'         => $session?->id,
            'sale_number'             => $number,
            'client_name'             => $validated['client_name'] ?? null,
            'sale_date'               => now(),
            'subtotal'                => $quote['subtotal'],
            'tax'                     => $quote['tax'],
            'discount'                => round($quote['line_discounts'] + $quote['global_discount'], 2),
            'membership_discount'     => $quote['membership_discount'],
            'package_discount'        => $quote['package_discount'],
            'loyalty_points_redeemed' => $quote['redeem_points'],
            'loyalty_discount'        => $quote['loyalty_discount'],
            'total'                   => $quote['total'],
            'tip'                     => $quote['tip'],
            'credit_applied'          => $quote['credit_applied'],
            'quote_id'                => $this->convertibleQuoteId($validated['quote_id'] ?? null, $companyId),
            'payment_method'          => $validated['payment_method'],
            'status'                  => 'completada',
            'notes'                   => $validated['notes'] ?? null,
            'created_by'              => auth()->id(),
        ]);

        foreach ($quote['lines'] as $line) {
            // Sin profesional en la línea, hereda el principal de la venta.
            $personalId = $line['personal_id'] ?: $principalId;

            // Un combo se guarda desarmado: una línea por componente.
            if ($line['kind'] === 'combo') {
                foreach ($line['components'] as $c) {
                    $sale->items()->create([
                        'service_id'  => $c['service_id'],
                        'product_id'  => $c['product_id'],
                        'combo_id'    => $line['combo_id'],
                        'personal_id' => $personalId,
                        'description' => $c['description'],
                        'quantity'    => $c['quantity'],
                        'unit_price'  => $c['unit_price'],
                        'discount'    => $c['discount'],
                        'total'       => $c['total'],
                    ]);
                    if ($c['product_id']) {
                        $this->stock->move($c['product_id'], -$c['quantity'], 'salida', $companyId, $number, 'Venta ' . $number);
                    }
                }
                continue;
            }

            $item = $sale->items()->create([
                'service_id'           => $line['service_id'],
                'product_id'           => $line['product_id'],
                'membership_plan_id'   => $line['membership_plan_id'],
                'service_package_id'   => $line['service_package_id'],
                'client_membership_id' => $line['client_membership_id'],
                'client_package_id'    => $line['client_package_id'],
                'uses_session'         => $line['uses_session'],
                'personal_id'          => $personalId,
                'description'          => $line['description'] ?: $this->lineName($line),
                'quantity'             => $line['quantity'],
                'unit_price'           => $line['unit_price'],
                'discount'             => $line['discount'],
                'membership_discount'  => $line['membership_discount'],
                'package_discount'     => $line['package_discount'],
                'total'                => $line['total'],
            ]);

            if ($line['product_id']) {
                $this->stock->move($line['product_id'], -$line['quantity'], 'salida', $companyId, $number, 'Venta ' . $number);
            }

            if ($line['membership_plan_id']) {
                $this->memberships->activateFromSale($sale, $item, MembershipPlan::withoutGlobalScopes()->findOrFail($line['membership_plan_id']));
            }

            if ($line['service_package_id']) {
                $this->packages->activateFromSale($sale, $item, ServicePackage::withoutGlobalScopes()->findOrFail($line['service_package_id']));
            }
        }

        if ($sale->quote_id) {
            Quote::withoutGlobalScopes()->whereKey($sale->quote_id)->update(['status' => 'convertida', 'sale_id' => $sale->id, 'converted_at' => now()]);
        }

        // Comisiones: se calculan ahora con las reglas vigentes y no se recalculan.
        $this->commissions->accrue($sale);

        // Puntos: primero el canje (ya descontado en el total), luego lo ganado.
        if ($quote['redeem_points'] > 0) {
            $this->loyalty->redeem($sale, $quote['redeem_points'], $quote['loyalty_discount']);
        }
        if ($this->loyalty->enabled($sale->company)) {
            $this->loyalty->earn($sale);
        }

        if ($quote['credit_applied'] > 0) {
            $this->credit->useInSale($sale, $quote['credit_applied']);
        }

        // Ingreso en caja si hay sesión abierta: solo lo que entra en dinero
        // (lo pagado con saldo a favor no entra). La propina va en un
        // movimiento aparte: entra a la caja pero no es venta del salón.
        if ($session) {
            $money = round($quote['total'] - $quote['credit_applied'], 2);
            if ($money > 0) {
                $session->movements()->create([
                    'type'           => 'ingreso',
                    'amount'         => $money,
                    'concept'        => 'Venta ' . $number,
                    'payment_method' => $validated['payment_method'],
                    'reference'      => (string) $sale->id,
                    'created_by'     => auth()->id(),
                ]);
            }

            if ($quote['tip'] > 0) {
                $session->movements()->create([
                    'type'           => 'ingreso',
                    'amount'         => $quote['tip'],
                    'concept'        => 'Propina venta ' . $number,
                    'category'       => 'Propinas',
                    'payment_method' => $validated['payment_method'],
                    'reference'      => (string) $sale->id,
                    'created_by'     => auth()->id(),
                ]);
            }
        }

        return $sale;
    }

    protected function lineName(array $line): ?string
    {
        return match (true) {
            (bool) $line['product_id']         => Product::find($line['product_id'])?->name,
            (bool) $line['membership_plan_id'] => 'Membresía ' . MembershipPlan::withoutGlobalScopes()->find($line['membership_plan_id'])?->name,
            (bool) $line['service_package_id'] => 'Paquete ' . ServicePackage::withoutGlobalScopes()->find($line['service_package_id'])?->name,
            (bool) $line['service_id']         => \App\Models\Service::find($line['service_id'])?->name,
            default                            => null,
        };
    }

    /** La cotización de origen solo se enlaza si es de la empresa y sigue sin convertir ni anular. */
    protected function convertibleQuoteId($quoteId, int $companyId): ?int
    {
        if (!$quoteId) {
            return null;
        }

        $quote = Quote::withoutGlobalScopes()->where('company_id', $companyId)->whereKey($quoteId)->lockForUpdate()->first();

        return $quote && $quote->canConvert() ? $quote->id : null;
    }

    protected function companyId(): ?int
    {
        return auth()->user()->getCurrentCompany()?->id;
    }

    protected function nextSaleNumber(int $companyId): string
    {
        $count = Sale::withTrashed()->where('company_id', $companyId)->count() + 1;

        return CompanySetting::current($companyId)->folio($count);
    }

    protected function authorizeSale(Sale $sale): void
    {
        if ($sale->company_id !== $this->companyId()) {
            abort(403);
        }
    }
}
