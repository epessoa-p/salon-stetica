<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Models\CashSession;
use App\Models\CompanySetting;
use App\Models\InventoryMovement;
use App\Models\MembershipPlan;
use App\Models\Product;
use App\Models\Sale;
use App\Services\CheckoutService;
use App\Services\CommissionService;
use App\Services\LoyaltyService;
use App\Services\MembershipService;
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
            'client', 'session.register', 'specialist', 'tipSettlement',
        ]);

        return view('sales.sales.show', ['sale' => $sale]);
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

        [$reversals, $canceled] = DB::transaction(function () use ($sale) {
            $sale->update(['status' => 'anulada']);

            $reversals = $this->commissions->reverseForVoidedSale($sale);
            $canceled = $this->memberships->cancelForVoidedSale($sale);
            $this->loyalty->reverseForVoidedSale($sale);

            foreach ($sale->items()->whereNotNull('product_id')->get() as $item) {
                $this->moveStock((int) $item->product_id, (float) $item->quantity, 'entrada', $sale, 'Anulación venta ' . $sale->sale_number);
            }

            $session = $sale->session;
            if ($session && $session->isOpen()) {
                $session->movements()->create([
                    'type'           => 'egreso',
                    'amount'         => $sale->total,
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

            return [$reversals, $canceled];
        });

        $message = 'Venta anulada.';
        if ($reversals > 0) {
            $message .= " {$reversals} comisión(es) ya liquidada(s) quedaron en pie: se generó su reverso negativo,"
                . ' que se descontará en la próxima liquidación del profesional.';
        }
        if ($canceled > 0) {
            $message .= " Se canceló {$canceled} membresía(s) vendida(s) en esta venta.";
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
            'loyalty_points_redeemed' => $quote['redeem_points'],
            'loyalty_discount'        => $quote['loyalty_discount'],
            'total'                   => $quote['total'],
            'tip'                     => $quote['tip'],
            'payment_method'          => $validated['payment_method'],
            'status'                  => 'completada',
            'notes'                   => $validated['notes'] ?? null,
            'created_by'              => auth()->id(),
        ]);

        foreach ($quote['lines'] as $line) {
            $item = $sale->items()->create([
                'service_id'           => $line['service_id'],
                'product_id'           => $line['product_id'],
                'membership_plan_id'   => $line['membership_plan_id'],
                'client_membership_id' => $line['client_membership_id'],
                'uses_session'         => $line['uses_session'],
                // Sin profesional en la línea, hereda el principal de la venta.
                'personal_id'          => $line['personal_id'] ?: $principalId,
                'description'          => $line['description'] ?: $this->lineName($line),
                'quantity'             => $line['quantity'],
                'unit_price'           => $line['unit_price'],
                'discount'             => $line['discount'],
                'membership_discount'  => $line['membership_discount'],
                'total'                => $line['total'],
            ]);

            if ($line['product_id']) {
                $this->moveStock($line['product_id'], -$line['quantity'], 'salida', $sale, 'Venta ' . $number);
            }

            if ($line['membership_plan_id']) {
                $this->memberships->activateFromSale($sale, $item, MembershipPlan::withoutGlobalScopes()->findOrFail($line['membership_plan_id']));
            }
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

        // Ingreso en caja si hay sesión abierta. La propina va en un
        // movimiento aparte: entra a la caja pero no es venta del salón.
        if ($session) {
            $session->movements()->create([
                'type'           => 'ingreso',
                'amount'         => $quote['total'],
                'concept'        => 'Venta ' . $number,
                'payment_method' => $validated['payment_method'],
                'reference'      => (string) $sale->id,
                'created_by'     => auth()->id(),
            ]);

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
            (bool) $line['service_id']         => \App\Models\Service::find($line['service_id'])?->name,
            default                            => null,
        };
    }

    /**
     * Mueve el stock de un producto y deja el movimiento de inventario.
     * $delta negativo = sale stock. Sin stock suficiente se cancela la venta.
     */
    protected function moveStock(int $productId, float $delta, string $type, Sale $sale, string $note): void
    {
        $product = Product::whereKey($productId)->lockForUpdate()->first();
        if (!$product) {
            return;
        }

        $current = (float) $product->current_stock;
        if ($delta < 0 && abs($delta) > $current) {
            throw new DomainException("No hay stock suficiente de {$product->name}. Disponible: " . rtrim(rtrim(number_format($current, 2), '0'), '.'));
        }

        $product->update(['current_stock' => max(0, $current + $delta)]);

        InventoryMovement::create([
            'company_id'    => $sale->company_id,
            'product_id'    => $product->id,
            'user_id'       => auth()->id(),
            'type'          => $type,
            'quantity'      => abs($delta),
            'unit_cost'     => $product->cost,
            'reference'     => $sale->sale_number,
            'notes'         => $note,
            'movement_date' => now(),
        ]);
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
