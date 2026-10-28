<?php

namespace App\Services;

use App\Models\Caja;
use App\Models\CashMovement;
use App\Models\CashSession;
use App\Models\Commission;
use App\Models\CommissionRule;
use App\Models\CommissionSettlement;
use App\Models\Personal;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Service;
use App\Models\User;
use DomainException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Módulo de comisiones: devengo al cobrar, reverso al anular y liquidación.
 *
 * Decisiones de negocio de Glamora (se muestran también en la pantalla de reglas):
 *  - Base = total de la línea (cantidad × precio − descuento de la línea).
 *    El descuento global, el canje de puntos y el descuento de membresía los
 *    asume el salón; la propina va aparte. Una sesión incluida en una
 *    membresía comisiona sobre su valor proporcional (ver baseFor()).
 *  - El monto fijo se paga por unidad (valor × cantidad).
 *  - Anular una venta borra sus comisiones pendientes y, por cada comisión ya
 *    liquidada, genera una comisión negativa pendiente que se descuenta sola
 *    en la siguiente liquidación del profesional.
 */
class CommissionService
{
    // ------------------------------------------------------------------
    //  Devengo
    // ------------------------------------------------------------------

    /**
     * Genera las comisiones de una venta recién cobrada. Se llama dentro de la
     * misma transacción que registra la venta. Devuelve cuántas se crearon.
     */
    public function accrue(Sale $sale): int
    {
        if (!$sale->company?->planAllows('commissions')) {
            return 0;
        }

        $rules = $this->rulesFor($sale->company_id);
        if ($rules->isEmpty()) {
            return 0;
        }

        $items = $sale->items()->with(['clientMembership', 'clientPackage.items'])->get();
        $categories = $this->categoriesFor($items);
        $now = now();
        $created = 0;

        foreach ($items as $item) {
            // El profesional va por línea; si la línea no lo trae, hereda el de la venta.
            $personalId = $item->personal_id ?: $sale->personal_id;
            if (!$personalId) {
                continue;
            }

            $categoryId = $categories[(int) $item->service_id] ?? null;
            $rule = $this->winningRule($rules, $item, (int) $personalId, $categoryId, $now);
            if (!$rule) {
                continue;
            }

            $base = $this->baseFor($item);
            $amount = $rule->amountFor($base, (float) $item->quantity);
            if ($amount <= 0) {
                continue;
            }

            Commission::create([
                'company_id'     => $sale->company_id,
                'personal_id'    => $personalId,
                'sale_id'        => $sale->id,
                'sale_detail_id' => $item->id,
                'rule_id'        => $rule->id,
                'base'           => $base,
                'calc_type'      => $rule->type,
                'calc_value'     => $rule->value,
                'rate'           => $rule->type === 'percent' ? $rule->value : 0,
                'amount'         => $amount,
                'status'         => 'pendiente',
                'accrued_at'     => $now,
                'period_start'   => $now->toDateString(),
                'period_end'     => $now->toDateString(),
                'created_by'     => auth()->id(),
            ]);
            $created++;
        }

        return $created;
    }

    /**
     * Regla ganadora para una línea: la activa y vigente de mayor prioridad.
     * Ante un empate (no debería ocurrir: el formulario impide reglas
     * solapadas) gana la más reciente, para que el resultado sea determinista.
     */
    public function winningRule(Collection $rules, SaleItem $item, ?int $personalId, ?int $categoryId, Carbon $on): ?CommissionRule
    {
        return $rules
            ->filter(fn (CommissionRule $r) => $r->matches($item, $personalId, $categoryId, $on))
            ->sort(fn (CommissionRule $a, CommissionRule $b) => [$b->priority(), $b->id] <=> [$a->priority(), $a->id])
            ->first();
    }

    /**
     * Base de la comisión de una línea:
     *  - normal: total de la línea + descuento de membresía (ese descuento lo
     *    asume el salón, no reduce la comisión);
     *  - sesión incluida en una membresía o de un paquete (se cobró en 0): lo
     *    que el salón cobró por cada sesión × cantidad.
     */
    protected function baseFor(SaleItem $item): float
    {
        if ($item->uses_session && $item->clientPackage) {
            $unit = $item->clientPackage->items->firstWhere('service_id', $item->service_id)?->unit_value ?? 0;

            return round((float) $unit * (float) $item->quantity, 2);
        }
        if ($item->uses_session && $item->clientMembership) {
            return round((float) $item->clientMembership->session_unit_value * (float) $item->quantity, 2);
        }

        // Membresía y promoción los asume el salón: no bajan la comisión.
        return round((float) $item->total + (float) $item->membership_discount + (float) $item->promotion_discount, 2);
    }

    /** Reglas activas de la empresa (sin depender del tenant de la petición). */
    public function rulesFor(int $companyId): Collection
    {
        return CommissionRule::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('active', true)
            ->get();
    }

    /** service_id => service_category_id de los servicios de las líneas. */
    protected function categoriesFor(Collection $items): array
    {
        $ids = $items->pluck('service_id')->filter()->unique()->values();

        if ($ids->isEmpty()) {
            return [];
        }

        return Service::withoutGlobalScopes()
            ->whereIn('id', $ids)
            ->pluck('service_category_id', 'id')
            ->map(fn ($v) => $v ? (int) $v : null)
            ->all();
    }

    // ------------------------------------------------------------------
    //  Anulación
    // ------------------------------------------------------------------

    /**
     * Ajusta las comisiones de una venta anulada:
     *  - borra las pendientes (incluidos reversos aún no liquidados),
     *  - por cada comisión ya liquidada crea su negativo pendiente.
     * Devuelve cuántos reversos se generaron.
     */
    public function reverseForVoidedSale(Sale $sale): int
    {
        Commission::withoutGlobalScopes()
            ->where('sale_id', $sale->id)
            ->where('status', 'pendiente')
            ->whereNull('settlement_id')
            ->delete();

        $settled = Commission::withoutGlobalScopes()
            ->where('sale_id', $sale->id)
            // En SQL "!= 'reversal'" descarta los NULL: se incluyen explícitamente.
            ->where(fn ($q) => $q->whereNull('calc_type')->orWhere('calc_type', '!=', 'reversal'))
            ->where(fn ($q) => $q->whereNotNull('settlement_id')->orWhere('status', 'pagada'))
            ->get();

        $now = now();
        $reversals = 0;

        foreach ($settled as $original) {
            // Idempotente: un mismo original no se revierte dos veces.
            $exists = Commission::withoutGlobalScopes()->where('reverses_id', $original->id)->exists();
            if ($exists) {
                continue;
            }

            Commission::create([
                'company_id'     => $original->company_id,
                'personal_id'    => $original->personal_id,
                'sale_id'        => $original->sale_id,
                'sale_detail_id' => $original->sale_detail_id,
                'rule_id'        => $original->rule_id,
                'reverses_id'    => $original->id,
                'base'           => -1 * (float) $original->base,
                'calc_type'      => 'reversal',
                'calc_value'     => $original->calc_value,
                'rate'           => 0,
                'amount'         => -1 * (float) $original->amount,
                'status'         => 'pendiente',
                'accrued_at'     => $now,
                'period_start'   => $now->toDateString(),
                'period_end'     => $now->toDateString(),
                'notes'          => 'Reverso de comisión liquidada por anulación de la venta ' . $sale->sale_number,
                'created_by'     => auth()->id(),
            ]);
            $reversals++;
        }

        return $reversals;
    }

    /**
     * Devolución parcial: por cada línea devuelta, una comisión negativa
     * pendiente proporcional a la cantidad devuelta (se descuenta en la
     * próxima liquidación del profesional, esté o no pagada la original).
     *
     * @param  array<int, float>  $quantities  sale_detail_id => cantidad devuelta
     */
    public function reverseForReturn(Sale $sale, array $quantities, string $returnNumber): int
    {
        $originals = Commission::withoutGlobalScopes()
            ->with('saleItem')
            ->where('sale_id', $sale->id)
            ->whereIn('sale_detail_id', array_keys($quantities))
            ->where(fn ($q) => $q->whereNull('calc_type')->orWhere('calc_type', '!=', 'reversal'))
            ->get();

        $now = now();
        $created = 0;

        foreach ($originals as $original) {
            $soldQty = (float) ($original->saleItem?->quantity ?? 0);
            $share = $soldQty > 0 ? min(1, ($quantities[$original->sale_detail_id] ?? 0) / $soldQty) : 0;
            $amount = round((float) $original->amount * $share, 2);
            if ($amount <= 0) {
                continue;
            }

            Commission::create([
                'company_id'     => $original->company_id,
                'personal_id'    => $original->personal_id,
                'sale_id'        => $original->sale_id,
                'sale_detail_id' => $original->sale_detail_id,
                'rule_id'        => $original->rule_id,
                'reverses_id'    => $original->id,
                'base'           => -round((float) $original->base * $share, 2),
                'calc_type'      => 'reversal',
                'calc_value'     => $original->calc_value,
                'rate'           => 0,
                'amount'         => -$amount,
                'status'         => 'pendiente',
                'accrued_at'     => $now,
                'period_start'   => $now->toDateString(),
                'period_end'     => $now->toDateString(),
                'notes'          => 'Reverso por devolución ' . $returnNumber . ' de la venta ' . $sale->sale_number,
                'created_by'     => auth()->id(),
            ]);
            $created++;
        }

        return $created;
    }

    // ------------------------------------------------------------------
    //  Liquidación
    // ------------------------------------------------------------------

    /** Comisiones pendientes de un profesional en el periodo (incluye reversos). */
    public function pendingCommissions(int $companyId, int $personalId, Carbon $from, Carbon $to)
    {
        return Commission::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('personal_id', $personalId)
            ->pending()
            ->whereBetween('accrued_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()]);
    }

    /** Ventas cobradas del periodo cuya propina todavía no se pagó. */
    public function pendingTips(int $companyId, int $personalId, Carbon $from, Carbon $to)
    {
        return Sale::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('personal_id', $personalId)
            ->where('status', '!=', 'anulada')
            ->where('tip', '>', 0)
            ->whereNull('tip_settlement_id')
            ->whereBetween('sale_date', [$from->toDateString(), $to->toDateString()]);
    }

    /** ¿El salón trabaja con caja? Si tiene alguna caja activa, sí. */
    public function companyUsesCash(int $companyId): bool
    {
        return Caja::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('active', true)
            ->exists();
    }

    /**
     * Paga al profesional sus comisiones pendientes del periodo (y opcionalmente
     * sus propinas) en una sola transacción. Lanza DomainException con un
     * mensaje para el usuario si no se puede liquidar.
     */
    public function settle(
        Personal $personal,
        Carbon $from,
        Carbon $to,
        bool $includeTips,
        ?int $cashSessionId,
        ?string $notes,
        User $payer
    ): CommissionSettlement {
        $companyId = (int) $personal->company_id;

        return DB::transaction(function () use ($personal, $companyId, $from, $to, $includeTips, $cashSessionId, $notes, $payer) {
            // Se bloquean las filas para que dos pagos simultáneos no tomen lo mismo.
            $commissions = $this->pendingCommissions($companyId, $personal->id, $from, $to)->lockForUpdate()->get();
            $tipSales = $includeTips
                ? $this->pendingTips($companyId, $personal->id, $from, $to)->lockForUpdate()->get()
                : collect();

            if ($commissions->isEmpty() && $tipSales->isEmpty()) {
                throw new DomainException('No hay nada pendiente de liquidar en ese periodo.');
            }

            $commissionsTotal = round((float) $commissions->sum('amount'), 2);
            $tipsTotal = round((float) $tipSales->sum('tip'), 2);
            $total = round($commissionsTotal + $tipsTotal, 2);

            if ($total <= 0) {
                throw new DomainException(
                    'El saldo del periodo es ' . number_format($total, 2) . ': los reversos por ventas anuladas superan lo pendiente. '
                    . 'Amplía el periodo para que se descuenten de comisiones posteriores.'
                );
            }

            // Si el salón usa caja, el dinero sale de un turno abierto.
            $session = null;
            if ($this->companyUsesCash($companyId)) {
                $session = $cashSessionId
                    ? CashSession::where('id', $cashSessionId)
                        ->where('status', 'open')
                        ->whereHas('register', fn ($q) => $q->withoutGlobalScopes()->where('company_id', $companyId))
                        ->first()
                    : null;

                if (!$session) {
                    throw new DomainException('Abre un turno de caja antes de liquidar: el pago sale de la caja.');
                }
            }

            $settlement = CommissionSettlement::create([
                'company_id'        => $companyId,
                'personal_id'       => $personal->id,
                'number'            => CommissionSettlement::nextNumber($companyId),
                'period_start'      => $from->toDateString(),
                'period_end'        => $to->toDateString(),
                'commissions_count' => $commissions->count(),
                'commissions_total' => $commissionsTotal,
                'tips_total'        => $tipsTotal,
                'total'             => $total,
                'include_tips'      => $includeTips,
                'paid_at'           => now(),
                'paid_by'           => $payer->id,
                'cash_session_id'   => $session?->id,
                'notes'             => $notes,
            ]);

            if ($session) {
                $movement = CashMovement::create([
                    'company_id'      => $companyId,
                    'cash_session_id' => $session->id,
                    'type'            => 'egreso',
                    'amount'          => $total,
                    'concept'         => 'Liquidación ' . $settlement->number . ' · ' . $personal->full_name,
                    'category'        => 'Comisiones',
                    'movement_date'   => now()->toDateString(),
                    'payment_method'  => 'efectivo',
                    'reference'       => $settlement->number,
                    'created_by'      => $payer->id,
                ]);

                $settlement->update(['cash_movement_id' => $movement->id]);
            }

            Commission::withoutGlobalScopes()
                ->whereIn('id', $commissions->pluck('id'))
                ->update([
                    'settlement_id' => $settlement->id,
                    'status'        => 'pagada',
                    'paid_at'       => now(),
                    'paid_by'       => $payer->id,
                ]);

            if ($tipSales->isNotEmpty()) {
                Sale::withoutGlobalScopes()
                    ->whereIn('id', $tipSales->pluck('id'))
                    ->update(['tip_settlement_id' => $settlement->id]);
            }

            return $settlement;
        });
    }
}
