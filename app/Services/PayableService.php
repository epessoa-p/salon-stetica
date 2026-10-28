<?php

namespace App\Services;

use App\Models\CashMovement;
use App\Models\CashSession;
use App\Models\Payable;
use App\Models\PayablePayment;
use App\Models\PurchaseOrder;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Cuentas por pagar a proveedores.
 *
 * Reglas de Glamora:
 *  - Nace al recibir una orden de compra a crédito: monto = total de la
 *    orden, vence a los días de crédito pactados.
 *  - Cada pago (PAG-) sale como egreso "Pago a proveedores" (de la caja si se
 *    elige una abierta). Anularlo solo se puede mientras su caja siga abierta.
 *  - Una devolución al proveedor con nota de crédito descuenta de la deuda.
 */
class PayableService
{
    public function __construct(protected ReceivableService $receivables)
    {
    }

    public function createFromOrder(PurchaseOrder $order): Payable
    {
        return Payable::create([
            'company_id'        => $order->company_id,
            'supplier_id'       => $order->supplier_id,
            'purchase_order_id' => $order->id,
            'amount'            => (float) $order->total,
            'due_date'          => today()->addDays((int) ($order->credit_days ?? 0))->toDateString(),
            'status'            => (float) $order->total > 0 ? 'pending' : 'paid',
            'created_by'        => auth()->id(),
        ]);
    }

    /** @throws DomainException */
    public function pay(Payable $payable, float $amount, string $method, ?int $sessionId, ?string $notes = null): PayablePayment
    {
        return DB::transaction(function () use ($payable, $amount, $method, $sessionId, $notes) {
            $payable = Payable::withoutGlobalScopes()->whereKey($payable->id)->lockForUpdate()->firstOrFail();
            $amount = round($amount, 2);

            if ($payable->status !== 'pending') {
                throw new DomainException('La cuenta ya no tiene saldo pendiente.');
            }
            if ($amount <= 0 || $amount > $payable->balance() + 0.001) {
                throw new DomainException('El pago debe ser mayor a 0 y hasta ' . number_format($payable->balance(), 2) . '.');
            }

            $session = null;
            if ($sessionId) {
                $session = CashSession::where('id', $sessionId)->where('status', 'open')
                    ->whereHas('register', fn ($q) => $q->where('company_id', $payable->company_id))->first();
                if (!$session) {
                    throw new DomainException('La caja elegida no está abierta.');
                }
            }

            $number = $this->receivables->nextNumber($payable->company_id, 'PAG-', PayablePayment::class);
            $movement = CashMovement::create([
                'company_id'      => $payable->company_id,
                'cash_session_id' => $session?->id,
                'type'            => 'egreso',
                'amount'          => $amount,
                'concept'         => 'Pago ' . $number . ' · ' . $payable->supplierDisplay() . ($payable->order ? ' · OC ' . $payable->order->entry_number : ''),
                'category'        => 'Pago a proveedores',
                'movement_date'   => today(),
                'payment_method'  => $method,
                'reference'       => $number,
                'created_by'      => auth()->id(),
            ]);

            $payment = PayablePayment::create([
                'company_id'       => $payable->company_id,
                'payable_id'       => $payable->id,
                'receipt_number'   => $number,
                'amount'           => $amount,
                'payment_method'   => $method,
                'cash_session_id'  => $session?->id,
                'cash_movement_id' => $movement->id,
                'paid_at'          => now(),
                'notes'            => $notes,
                'created_by'       => auth()->id(),
            ]);

            $payable->update(['paid_amount' => round((float) $payable->paid_amount + $amount, 2)]);
            $this->refreshStatus($payable);

            return $payment;
        });
    }

    public function voidPayment(PayablePayment $payment): void
    {
        DB::transaction(function () use ($payment) {
            $session = $payment->cash_session_id ? CashSession::find($payment->cash_session_id) : null;
            if ($session && !$session->isOpen()) {
                throw new DomainException('La caja de este pago ya se cerró: no se puede anular.');
            }

            CashMovement::withoutGlobalScopes()->find($payment->cash_movement_id)?->delete();
            $payable = Payable::withoutGlobalScopes()->lockForUpdate()->find($payment->payable_id);
            $payment->delete();
            $payable->update(['paid_amount' => max(0, round((float) $payable->paid_amount - (float) $payment->amount, 2))]);
            $this->refreshStatus($payable);
        });
    }

    /** Nota de crédito por devolución: descuenta de la deuda de esa orden. Devuelve cuánto se aplicó. */
    public function applyReturn(PurchaseOrder $order, float $amount): float
    {
        $payable = Payable::withoutGlobalScopes()->where('purchase_order_id', $order->id)->where('status', 'pending')->lockForUpdate()->first();
        if (!$payable || $amount <= 0) {
            return 0.0;
        }

        $applied = round(min($amount, $payable->balance()), 2);
        $payable->update(['adjusted_amount' => round((float) $payable->adjusted_amount + $applied, 2)]);
        $this->refreshStatus($payable);

        return $applied;
    }

    protected function refreshStatus(Payable $payable): void
    {
        $status = $payable->balance() <= 0 ? 'paid' : 'pending';
        if ($payable->status !== 'canceled' && $payable->status !== $status) {
            $payable->update(['status' => $status]);
        }
    }
}
