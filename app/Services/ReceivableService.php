<?php

namespace App\Services;

use App\Models\CashMovement;
use App\Models\CashSession;
use App\Models\Client;
use App\Models\CompanySetting;
use App\Models\Receivable;
use App\Models\ReceivablePayment;
use App\Models\Sale;
use DomainException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Cuentas por cobrar (ventas a crédito).
 *
 * Reglas de Glamora:
 *  - Solo se vende a crédito a un cliente con límite de crédito definido; la
 *    deuda abierta más la nueva no puede superarlo.
 *  - Comisiones y puntos nacen al vender, como en cualquier venta.
 *  - Un cobro (recibo REC-) se aplica a la cuenta elegida o, si no se elige,
 *    a las más antiguas primero. Entra como ingreso "Cobros de cuentas" (en
 *    la caja si se elige una abierta).
 *  - Anular un recibo solo se puede mientras su caja siga abierta.
 *  - Una devolución sobre una venta a crédito descuenta primero de la deuda.
 *  - Una venta a crédito con abonos no se anula (se registra una devolución).
 */
class ReceivableService
{
    /** Deuda abierta del cliente. */
    public function openBalance(int $companyId, int $clientId): float
    {
        return round((float) Receivable::withoutGlobalScopes()
            ->where('company_id', $companyId)->where('client_id', $clientId)->where('status', 'pending')
            ->sum(DB::raw('amount - paid_amount - adjusted_amount')), 2);
    }

    public function hasOverdue(int $companyId, int $clientId): bool
    {
        return Receivable::withoutGlobalScopes()
            ->where('company_id', $companyId)->where('client_id', $clientId)->where('status', 'pending')
            ->where('due_date', '<', today()->toDateString())->exists();
    }

    /** Resumen para el POS y la ficha: deuda, límite y disponible. */
    public function summary(Client $client): array
    {
        $open = $this->openBalance($client->company_id, $client->id);
        $limit = $client->credit_limit === null ? null : (float) $client->credit_limit;

        return [
            'enabled'   => $limit !== null,
            'limit'     => $limit,
            'debt'      => $open,
            'available' => $limit === null ? 0.0 : max(0, round($limit - $open, 2)),
            'overdue'   => $this->hasOverdue($client->company_id, $client->id),
        ];
    }

    /** Motivo por el que no se puede fiar este monto, o null si se puede. */
    public function creditError(Client $client, float $amount): ?string
    {
        $s = $this->summary($client);
        if (!$s['enabled']) {
            return 'El cliente no tiene crédito habilitado: define su límite de crédito en su ficha.';
        }
        if ($amount > $s['available'] + 0.001) {
            return 'Supera el límite de crédito del cliente: disponible ' . number_format($s['available'], 2)
                . ' (límite ' . number_format($s['limit'], 2) . ', debe ' . number_format($s['debt'], 2) . ').';
        }

        return null;
    }

    public function createFromSale(Sale $sale, float $amount, string $dueDate): Receivable
    {
        return Receivable::create([
            'company_id' => $sale->company_id,
            'client_id'  => $sale->client_id,
            'sale_id'    => $sale->id,
            'amount'     => round($amount, 2),
            'due_date'   => $dueDate,
            'status'     => 'pending',
            'created_by' => auth()->id(),
        ]);
    }

    /** Cuentas pendientes del cliente, la más antigua primero. */
    public function pendingFor(int $companyId, int $clientId): Collection
    {
        return Receivable::withoutGlobalScopes()->with('sale')
            ->where('company_id', $companyId)->where('client_id', $clientId)->where('status', 'pending')
            ->orderBy('due_date')->orderBy('id')
            ->get();
    }

    /**
     * Registra un cobro. Sin $receivableId se reparte entre las cuentas más
     * antiguas. Devuelve el número de recibo.
     *
     * @throws DomainException
     */
    public function collect(Client $client, float $amount, string $method, ?int $sessionId, ?int $receivableId = null, ?string $notes = null): string
    {
        return DB::transaction(function () use ($client, $amount, $method, $sessionId, $receivableId, $notes) {
            $amount = round($amount, 2);
            $companyId = $client->company_id;

            $targets = Receivable::withoutGlobalScopes()
                ->where('company_id', $companyId)->where('client_id', $client->id)->where('status', 'pending')
                ->when($receivableId, fn ($q) => $q->whereKey($receivableId))
                ->orderBy('due_date')->orderBy('id')
                ->lockForUpdate()->get();

            $balance = round($targets->sum(fn ($r) => $r->balance()), 2);
            if ($amount <= 0) {
                throw new DomainException('Indica el monto a cobrar.');
            }
            if ($targets->isEmpty() || $amount > $balance + 0.001) {
                throw new DomainException('El cobro supera la deuda: se pueden cobrar hasta ' . number_format($balance, 2) . '.');
            }

            $session = $this->openSession($companyId, $sessionId);
            $number = $this->nextNumber($companyId, 'REC-', ReceivablePayment::class);

            $movement = CashMovement::create([
                'company_id'      => $companyId,
                'cash_session_id' => $session?->id,
                'type'            => 'ingreso',
                'amount'          => $amount,
                'concept'         => 'Cobro ' . $number . ' · ' . $client->full_name,
                'category'        => 'Cobros de cuentas',
                'movement_date'   => today(),
                'payment_method'  => $method,
                'reference'       => $number,
                'created_by'      => auth()->id(),
            ]);

            $left = $amount;
            foreach ($targets as $receivable) {
                if ($left <= 0) {
                    break;
                }
                $part = min($left, $receivable->balance());
                if ($part <= 0) {
                    continue;
                }
                ReceivablePayment::create([
                    'company_id'       => $companyId,
                    'receivable_id'    => $receivable->id,
                    'client_id'        => $client->id,
                    'receipt_number'   => $number,
                    'amount'           => $part,
                    'payment_method'   => $method,
                    'cash_session_id'  => $session?->id,
                    'cash_movement_id' => $movement->id,
                    'paid_at'          => now(),
                    'notes'            => $notes,
                    'created_by'       => auth()->id(),
                ]);
                $receivable->update(['paid_amount' => round((float) $receivable->paid_amount + $part, 2)]);
                $this->refreshStatus($receivable);
                $left = round($left - $part, 2);
            }

            return $number;
        });
    }

    /** Anula un recibo completo (todas sus partes). Solo con la caja aún abierta. */
    public function voidReceipt(int $companyId, string $number): void
    {
        DB::transaction(function () use ($companyId, $number) {
            $payments = ReceivablePayment::withoutGlobalScopes()->whereNull('deleted_at')->where('company_id', $companyId)->where('receipt_number', $number)->get();
            if ($payments->isEmpty()) {
                throw new DomainException('El recibo ya está anulado.');
            }
            $session = $payments->first()->cash_session_id ? CashSession::find($payments->first()->cash_session_id) : null;
            if ($session && !$session->isOpen()) {
                throw new DomainException('La caja de este cobro ya se cerró: no se puede anular. Registra un ajuste en la caja actual.');
            }

            CashMovement::withoutGlobalScopes()->find($payments->first()->cash_movement_id)?->delete();

            foreach ($payments as $payment) {
                $receivable = Receivable::withoutGlobalScopes()->lockForUpdate()->find($payment->receivable_id);
                $payment->delete();
                if ($receivable) {
                    $receivable->update(['paid_amount' => max(0, round((float) $receivable->paid_amount - (float) $payment->amount, 2))]);
                    $this->refreshStatus($receivable);
                }
            }
        });
    }

    /** Devolución sobre una venta a crédito: descuenta de la deuda. Devuelve cuánto se aplicó. */
    public function applyReturn(Sale $sale, float $amount): float
    {
        $receivable = Receivable::withoutGlobalScopes()->where('sale_id', $sale->id)->where('status', 'pending')->lockForUpdate()->first();
        if (!$receivable || $amount <= 0) {
            return 0.0;
        }

        $applied = round(min($amount, $receivable->balance()), 2);
        $receivable->update(['adjusted_amount' => round((float) $receivable->adjusted_amount + $applied, 2)]);
        $this->refreshStatus($receivable);

        return $applied;
    }

    /** Anulación de la venta: cancela la deuda si no tiene abonos. */
    public function cancelForVoidedSale(Sale $sale): void
    {
        $receivable = Receivable::withoutGlobalScopes()->where('sale_id', $sale->id)->first();
        if (!$receivable) {
            return;
        }
        if ((float) $receivable->paid_amount > 0) {
            throw new DomainException('La venta a crédito ya tiene abonos: no se puede anular. Registra una devolución.');
        }

        $receivable->update(['status' => 'canceled']);
    }

    protected function refreshStatus(Receivable $receivable): void
    {
        $status = $receivable->balance() <= 0 ? 'paid' : 'pending';
        if ($receivable->status !== 'canceled' && $receivable->status !== $status) {
            $receivable->update(['status' => $status]);
        }
    }

    protected function openSession(int $companyId, ?int $sessionId): ?CashSession
    {
        if (!$sessionId) {
            if (CompanySetting::current($companyId)->require_cash_session) {
                throw new DomainException('Las preferencias de la empresa exigen registrar el cobro en una caja abierta.');
            }

            return null;
        }

        $session = CashSession::where('id', $sessionId)->where('status', 'open')
            ->whereHas('register', fn ($q) => $q->where('company_id', $companyId))->first();
        if (!$session) {
            throw new DomainException('La caja elegida no está abierta.');
        }

        return $session;
    }

    /** Consecutivo de recibos (cuenta los anulados para no repetir números). */
    public function nextNumber(int $companyId, string $prefix, string $model): string
    {
        $count = $model::withoutGlobalScopes()->withTrashed()->where('company_id', $companyId)->distinct()->count('receipt_number') + 1;

        return CompanySetting::current($companyId)->documentFolio($prefix, $count);
    }
}
