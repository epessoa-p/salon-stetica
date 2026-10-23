<?php

namespace App\Services;

use App\Models\ClientCreditMovement;
use App\Models\Sale;
use App\Models\SaleReturn;
use App\Models\User;

/**
 * Saldo a favor de los clientes. Nace de una devolución reintegrada como
 * saldo y se usa como forma de pago en el punto de venta. El saldo es la suma
 * de los movimientos; nunca se guarda un acumulado.
 */
class CreditService
{
    public function balance(int $companyId, int $clientId): float
    {
        return round((float) ClientCreditMovement::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('client_id', $clientId)
            ->sum('amount'), 2);
    }

    /** Saldo generado por una devolución. */
    public function fromReturn(SaleReturn $return): ClientCreditMovement
    {
        return ClientCreditMovement::create([
            'company_id'     => $return->company_id,
            'client_id'      => $return->client_id,
            'sale_id'        => $return->sale_id,
            'sale_return_id' => $return->id,
            'type'           => 'return',
            'amount'         => (float) $return->total,
            'notes'          => 'Devolución ' . $return->return_number,
            'created_by'     => auth()->id(),
        ]);
    }

    /** Saldo usado para pagar una venta. */
    public function useInSale(Sale $sale, float $amount): void
    {
        ClientCreditMovement::create([
            'company_id' => $sale->company_id,
            'client_id'  => $sale->client_id,
            'sale_id'    => $sale->id,
            'type'       => 'use',
            'amount'     => -round($amount, 2),
            'notes'      => 'Pago de la venta ' . $sale->sale_number,
            'created_by' => auth()->id(),
        ]);
    }

    /** Anulación: el saldo usado vuelve al cliente. Idempotente. */
    public function reverseForVoidedSale(Sale $sale): float
    {
        $used = -1 * (float) ClientCreditMovement::withoutGlobalScopes()
            ->where('sale_id', $sale->id)->where('type', 'use')->sum('amount');
        $reversed = ClientCreditMovement::withoutGlobalScopes()
            ->where('sale_id', $sale->id)->where('type', 'reverse')->exists();

        if ($used <= 0 || $reversed || !$sale->client_id) {
            return 0.0;
        }

        ClientCreditMovement::create([
            'company_id' => $sale->company_id,
            'client_id'  => $sale->client_id,
            'sale_id'    => $sale->id,
            'type'       => 'reverse',
            'amount'     => round($used, 2),
            'notes'      => 'Anulación de la venta ' . $sale->sale_number,
            'created_by' => auth()->id(),
        ]);

        return round($used, 2);
    }

    /** Ajuste manual con motivo (positivo suma, negativo resta). */
    public function adjust(int $companyId, int $clientId, float $amount, string $reason, User $by): ClientCreditMovement
    {
        return ClientCreditMovement::create([
            'company_id' => $companyId,
            'client_id'  => $clientId,
            'type'       => 'adjust',
            'amount'     => round($amount, 2),
            'notes'      => $reason,
            'created_by' => $by->id,
        ]);
    }
}
