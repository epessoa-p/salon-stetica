<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Paquete de sesiones prepagadas (ej. 10 depilaciones). Se vende en el
 * punto de venta y el cliente lo consume en visitas futuras.
 */
class ServicePackage extends Model
{
    use BelongsToCompany, HasFactory, SoftDeletes;

    protected $fillable = ['company_id', 'name', 'description', 'price', 'validity_days', 'active', 'sort_order', 'created_by'];

    protected $casts = [
        'price'         => 'decimal:2',
        'validity_days' => 'integer',
        'active'        => 'boolean',
        'deleted_at'    => 'datetime',
    ];

    /** Vigencias rápidas del formulario: días => etiqueta. */
    public const VALIDITIES = [
        30  => '1 mes',
        90  => '3 meses',
        180 => '6 meses',
        365 => '1 año',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(ServicePackageItem::class);
    }

    public function totalSessions(): int
    {
        return (int) $this->items->sum('quantity');
    }

    /** Lo que costarían las sesiones por separado. */
    public function listPrice(): float
    {
        return round($this->items->sum(fn ($i) => (float) ($i->service?->price ?? 0) * $i->quantity), 2);
    }

    public function validityLabel(): string
    {
        if (!$this->validity_days) {
            return 'Sin vencimiento';
        }

        return 'Vigencia ' . (self::VALIDITIES[$this->validity_days] ?? $this->validity_days . ' días');
    }

    /** "10× Depilación, 2× Limpieza" */
    public function summary(): string
    {
        return $this->items->map(fn ($i) => $i->quantity . '× ' . ($i->service?->name ?? '?'))->implode(', ');
    }
}
