<?php

namespace App\Models\Scopes;

use App\Support\Tenancy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Filtra automáticamente por la empresa activa (Tenancy).
 * Si no hay empresa activa (super admin / CLI), no aplica filtro.
 */
class CompanyScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $companyId = app(Tenancy::class)->id();

        if ($companyId !== null) {
            $builder->where($model->getTable() . '.company_id', $companyId);
        }
    }
}
