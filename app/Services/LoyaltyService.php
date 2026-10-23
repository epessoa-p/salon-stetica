<?php

namespace App\Services;

use App\Models\Company;
use App\Models\CompanySetting;
use App\Models\LoyaltyMovement;
use App\Models\Sale;
use App\Models\User;

/**
 * Programa de puntos. El saldo de un cliente es la suma de sus movimientos.
 *
 * Reglas de Glamora:
 *  - Se gana 1 punto por cada `loyalty_amount_per_point` del total pagado
 *    (sin propina), solo si la venta tiene cliente identificado.
 *  - Se canjea como dinero en el POS: puntos × `loyalty_point_value`.
 *  - Vence por inactividad: si pasan `loyalty_expiry_months` meses sin
 *    ganar, canjear ni ajustes, el saldo vence. NULL = no vence.
 *  - Anular una venta revierte lo ganado y devuelve lo canjeado. El saldo
 *    puede quedar negativo si ya se gastaron esos puntos.
 */
class LoyaltyService
{
    /** Movimientos que cuentan como actividad del cliente. */
    protected const ACTIVITY = ['earn', 'redeem', 'adjust'];

    /** ¿La empresa tiene el programa activo (plan + preferencia)? */
    public function enabled(?Company $company): bool
    {
        return $company
            && $company->planAllows('loyalty')
            && CompanySetting::current($company->id)->loyalty_enabled;
    }

    /** Saldo actual; antes aplica el vencimiento por inactividad. */
    public function balance(int $companyId, int $clientId, bool $applyExpiry = true): int
    {
        if ($applyExpiry) {
            $this->expireIfInactive($companyId, $clientId);
        }

        return (int) LoyaltyMovement::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('client_id', $clientId)
            ->sum('points');
    }

    /** Vence el saldo si el cliente lleva demasiado tiempo inactivo. Devuelve los puntos vencidos. */
    public function expireIfInactive(int $companyId, int $clientId): int
    {
        $months = CompanySetting::current($companyId)->loyalty_expiry_months;
        if (!$months) {
            return 0;
        }

        $query = LoyaltyMovement::withoutGlobalScopes()->where('company_id', $companyId)->where('client_id', $clientId);
        $lastActivity = (clone $query)->whereIn('type', self::ACTIVITY)->max('created_at');

        if (!$lastActivity || now()->subMonths($months)->lt($lastActivity)) {
            return 0;
        }

        $balance = (int) (clone $query)->sum('points');
        if ($balance <= 0) {
            return 0;
        }

        LoyaltyMovement::create([
            'company_id' => $companyId,
            'client_id'  => $clientId,
            'type'       => 'expire',
            'points'     => -$balance,
            'amount'     => CompanySetting::current($companyId)->pointsValue($balance),
            'notes'      => "Vencidos por {$months} meses sin actividad",
        ]);

        return $balance;
    }

    /** Resumen del saldo para el POS y la ficha del cliente. */
    public function summary(int $companyId, int $clientId, float $payable = PHP_FLOAT_MAX): array
    {
        $setting = CompanySetting::current($companyId);
        $balance = $this->balance($companyId, $clientId);
        $value = (float) $setting->loyalty_point_value;

        // Máximo canjeable: no más que el saldo ni que lo que hay por pagar.
        $byAmount = $value > 0 ? (int) floor($payable / $value) : 0;
        $max = max(0, min($balance, $byAmount));

        return [
            'balance'        => $balance,
            'value'          => $setting->pointsValue(max(0, $balance)),
            'point_value'    => $value,
            'min_redeem'     => (int) $setting->loyalty_min_redeem,
            'max_redeemable' => $max >= $setting->loyalty_min_redeem ? $max : 0,
        ];
    }

    /** Registra los puntos que gana una venta cobrada. */
    public function earn(Sale $sale): int
    {
        if (!$sale->client_id) {
            return 0;
        }

        $points = CompanySetting::current($sale->company_id)->pointsFor((float) $sale->total);
        if ($points <= 0) {
            return 0;
        }

        LoyaltyMovement::create([
            'company_id' => $sale->company_id,
            'client_id'  => $sale->client_id,
            'sale_id'    => $sale->id,
            'type'       => 'earn',
            'points'     => $points,
            'amount'     => (float) $sale->total,
            'notes'      => 'Compra ' . $sale->sale_number,
            'created_by' => auth()->id(),
        ]);

        $sale->update(['loyalty_points_earned' => $points]);

        return $points;
    }

    /** Registra el canje de una venta (el descuento ya va en la venta). */
    public function redeem(Sale $sale, int $points, float $amount): void
    {
        LoyaltyMovement::create([
            'company_id' => $sale->company_id,
            'client_id'  => $sale->client_id,
            'sale_id'    => $sale->id,
            'type'       => 'redeem',
            'points'     => -$points,
            'amount'     => $amount,
            'notes'      => 'Canje en venta ' . $sale->sale_number,
            'created_by' => auth()->id(),
        ]);
    }

    /** Anulación: revierte lo ganado y devuelve lo canjeado. Idempotente. */
    public function reverseForVoidedSale(Sale $sale): int
    {
        $movements = LoyaltyMovement::withoutGlobalScopes()
            ->where('sale_id', $sale->id)
            ->whereIn('type', ['earn', 'redeem'])
            ->get();

        $alreadyReversed = LoyaltyMovement::withoutGlobalScopes()->where('sale_id', $sale->id)->where('type', 'reverse')->exists();
        if ($movements->isEmpty() || $alreadyReversed) {
            return 0;
        }

        foreach ($movements as $m) {
            LoyaltyMovement::create([
                'company_id' => $m->company_id,
                'client_id'  => $m->client_id,
                'sale_id'    => $sale->id,
                'type'       => 'reverse',
                'points'     => -$m->points,
                'amount'     => $m->amount,
                'notes'      => ($m->type === 'earn' ? 'Se quitan los puntos ganados' : 'Se devuelven los puntos canjeados')
                    . ' · anulación ' . $sale->sale_number,
                'created_by' => auth()->id(),
            ]);
        }

        return $movements->count();
    }

    /** Ajuste manual con motivo (suma o resta). */
    public function adjust(int $companyId, int $clientId, int $points, string $reason, User $by): LoyaltyMovement
    {
        return LoyaltyMovement::create([
            'company_id' => $companyId,
            'client_id'  => $clientId,
            'type'       => 'adjust',
            'points'     => $points,
            'amount'     => CompanySetting::current($companyId)->pointsValue(abs($points)),
            'notes'      => $reason,
            'created_by' => $by->id,
        ]);
    }

    /** Vence en lote los saldos inactivos (comando loyalty:expire). */
    public function expireAll(?int $companyId = null): int
    {
        $total = 0;

        LoyaltyMovement::withoutGlobalScopes()
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->groupBy('company_id', 'client_id')
            ->havingRaw('SUM(points) > 0')
            ->select('company_id', 'client_id')
            ->get()
            ->each(function ($row) use (&$total) {
                $total += $this->expireIfInactive((int) $row->company_id, (int) $row->client_id);
            });

        return $total;
    }
}
