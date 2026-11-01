<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\CashSession;
use App\Models\Personal;

/**
 * Sucursal de una operación (venta, cita, movimiento de inventario), para
 * que con varias sucursales se sepa siempre de cuál es. Orden:
 *   1. la indicada (si es de la empresa),
 *   2. la de la caja donde se cobra,
 *   3. la del profesional o de quien registra,
 *   4. la única sucursal de la empresa.
 * Con varias sucursales y sin ninguna pista devuelve null.
 */
class BranchResolver
{
    public function resolve(int $companyId, ?int $branchId = null, ?int $cashSessionId = null, ?int $personalId = null, ?int $userId = null): ?int
    {
        if ($branchId && $this->belongs($companyId, $branchId)) {
            return $branchId;
        }

        if ($cashSessionId) {
            $fromCash = CashSession::whereKey($cashSessionId)->with('register:id,company_id,branch_id')->first()?->register;
            if ($fromCash && $fromCash->company_id === $companyId && $fromCash->branch_id) {
                return (int) $fromCash->branch_id;
            }
        }

        $personal = $personalId
            ? Personal::withoutGlobalScopes()->where('company_id', $companyId)->find($personalId)
            : ($userId ? Personal::withoutGlobalScopes()->where('company_id', $companyId)->where('user_id', $userId)->first() : null);
        if ($personal?->branch_id) {
            return (int) $personal->branch_id;
        }

        return $this->onlyBranch($companyId);
    }

    /** Sucursal con la que arranca un usuario en el POS o la agenda. */
    public function defaultFor(int $companyId, int $userId): ?int
    {
        return $this->resolve($companyId, userId: $userId);
    }

    /** Almacén principal de la sucursal (de donde salen sus productos). */
    public function warehouseOf(?int $branchId): ?int
    {
        return $branchId ? Branch::withoutGlobalScopes()->whereKey($branchId)->value('warehouse_id') : null;
    }

    protected function onlyBranch(int $companyId): ?int
    {
        $ids = Branch::withoutGlobalScopes()->whereNull('deleted_at')->where('company_id', $companyId)->where('active', true)->limit(2)->pluck('id');

        return $ids->count() === 1 ? (int) $ids->first() : null;
    }

    protected function belongs(int $companyId, int $branchId): bool
    {
        return Branch::withoutGlobalScopes()->whereNull('deleted_at')->where('company_id', $companyId)->whereKey($branchId)->exists();
    }
}
