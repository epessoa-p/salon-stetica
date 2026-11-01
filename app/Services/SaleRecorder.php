<?php

namespace App\Services;

use App\Models\CashSession;
use App\Models\CompanySetting;
use App\Models\MembershipPlan;
use App\Models\Product;
use App\Models\Quote;
use App\Models\Sale;
use App\Models\ServicePackage;

/**
 * Registra una venta ya cotizada: líneas, stock, membresías y paquetes,
 * comisiones, puntos, saldo a favor, cuenta por cobrar y caja. Lo usan el
 * cobro de la web (SaleController) y el de la app (API), así ambos guardan
 * exactamente lo mismo.
 */
class SaleRecorder
{
    public function __construct(
        protected CommissionService $commissions,
        protected MembershipService $memberships,
        protected LoyaltyService $loyalty,
        protected PackageService $packages,
        protected CreditService $credit,
        protected StockService $stock,
        protected ReceivableService $receivables,
        protected BranchResolver $branches,
    ) {
    }

    /**
     * Guarda la venta ya calculada por CheckoutService::quote(). Corre dentro
     * de una transacción (la abre quien llama).
     */
    public function record(array $validated, array $quote, int $companyId): Sale
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
            // La indicada, la de la caja, la del profesional o la única de la empresa.
            'branch_id'               => $this->branches->resolve($companyId, $validated['branch_id'] ?? null, $session?->id, $principalId, auth()->id()),
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
            'promotion_discount'      => $quote['promotion_discount'],
            'coupon_id'               => $quote['coupon']['id'] ?? null,
            'coupon_discount'         => $quote['coupon_discount'],
            'loyalty_points_redeemed' => $quote['redeem_points'],
            'loyalty_discount'        => $quote['loyalty_discount'],
            'total'                   => $quote['total'],
            'tip'                     => $quote['tip'],
            'credit_applied'          => $quote['credit_applied'],
            'on_credit'               => $quote['on_credit'],
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
                        $this->stock->move($c['product_id'], -$c['quantity'], 'salida', $companyId, $number, 'Venta ' . $number, $sale->branch_id);
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
                'promotion_id'         => $line['promotion_id'],
                'promotion_discount'   => $line['promotion_discount'],
                'total'                => $line['total'],
            ]);

            if ($line['product_id']) {
                $this->stock->move($line['product_id'], -$line['quantity'], 'salida', $companyId, $number, 'Venta ' . $number, $sale->branch_id);
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

        if ($quote['on_credit']) {
            $this->receivables->createFromSale($sale, $quote['pending'], $quote['due_date']);
        }

        // Ingreso en caja si hay sesión abierta: solo lo que entra en dinero
        // (lo pagado con saldo a favor no entra). La propina va en un
        // movimiento aparte: entra a la caja pero no es venta del salón.
        if ($session) {
            // A crédito entra solo el anticipo (menos la propina, que va aparte).
            $money = $quote['on_credit']
                ? round($quote['down_payment'] - $quote['tip'], 2)
                : round($quote['total'] - $quote['credit_applied'], 2);
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

    protected function nextSaleNumber(int $companyId): string
    {
        $count = Sale::withTrashed()->where('company_id', $companyId)->count() + 1;

        return CompanySetting::current($companyId)->folio($count);
    }
}
