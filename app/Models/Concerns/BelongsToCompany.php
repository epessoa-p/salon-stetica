<?php

namespace App\Models\Concerns;

use App\Models\Scopes\CompanyScope;
use App\Support\Tenancy;
use Illuminate\Database\Eloquent\Builder;

/**
 * Trait para modelos de tenant (tienen company_id).
 * - Aplica el filtro por empresa activa (CompanyScope).
 * - Autocompleta company_id al crear si viene vacío.
 * - scopeForCompany() permite consultar una empresa puntual saltando el scope global.
 */
trait BelongsToCompany
{
    public static function bootBelongsToCompany(): void
    {
        static::addGlobalScope(new CompanyScope);

        static::creating(function ($model) {
            if (empty($model->company_id)) {
                $companyId = app(Tenancy::class)->id();
                if ($companyId !== null) {
                    $model->company_id = $companyId;
                }
            }
        });
    }

    /** Consulta una empresa concreta ignorando el scope global de empresa. */
    public function scopeForCompany(Builder $query, int $companyId): Builder
    {
        return $query->withoutGlobalScope(CompanyScope::class)->where($this->getTable() . '.company_id', $companyId);
    }
}
