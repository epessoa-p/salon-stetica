<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/** Almacén: cada sucursal tiene uno principal (de ahí salen sus productos). */
class Warehouse extends Model
{
    use BelongsToCompany, SoftDeletes;

    protected $fillable = [
        'company_id',
        'name',
        'code',
        'location',
        'description',
        'active',
    ];

    protected $casts = [
        'active' => 'boolean',
        'deleted_at' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class);
    }

    /** Nombre con la(s) sucursal(es) que lo usan: «ALMACÉN NORTE · SUCURSAL NORTE». */
    public function label(): string
    {
        $branches = $this->relationLoaded('branches') ? $this->branches : $this->branches()->get(['id', 'warehouse_id', 'name']);

        return $this->name . ($branches->isNotEmpty() ? ' · ' . $branches->pluck('name')->implode(', ') : '');
    }

    /** Almacén principal para una sucursal nueva (el código es único en todo el sistema). */
    public static function createForBranch(int $companyId, string $branchName, ?string $location = null): self
    {
        return static::create([
            'company_id'  => $companyId,
            'name'        => 'ALMACÉN ' . mb_strtoupper($branchName),
            'code'        => 'WH-' . $companyId . '-' . strtoupper(Str::random(6)),
            'location'    => $location,
            'description' => 'Almacén principal de ' . $branchName,
            'active'      => true,
        ]);
    }
}
