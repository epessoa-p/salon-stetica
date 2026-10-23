<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Plan de membresía que vende el salón: un precio, una vigencia, un % de
 * descuento sobre un alcance y, opcionalmente, sesiones incluidas.
 */
class MembershipPlan extends Model
{
    use BelongsToCompany, HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id', 'name', 'description', 'price', 'duration_days', 'discount_percent',
        'discount_scope', 'service_category_id', 'active', 'sort_order', 'created_by',
    ];

    protected $casts = [
        'price'            => 'decimal:2',
        'discount_percent' => 'decimal:2',
        'duration_days'    => 'integer',
        'active'           => 'boolean',
        'deleted_at'       => 'datetime',
    ];

    /** Sobre qué se aplica el descuento. */
    public const SCOPES = [
        'services' => 'Todos los servicios',
        'category' => 'Una categoría de servicios',
        'products' => 'Todos los productos',
        'all'      => 'Servicios y productos',
    ];

    /** Duraciones rápidas del formulario (días => etiqueta). */
    public const DURATIONS = [
        30  => 'Mensual (30 días)',
        90  => 'Trimestral (90 días)',
        180 => 'Semestral (180 días)',
        365 => 'Anual (365 días)',
    ];

    public function sessions(): HasMany
    {
        return $this->hasMany(MembershipPlanSession::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ServiceCategory::class, 'service_category_id');
    }

    public function totalSessions(): int
    {
        return (int) $this->sessions->sum('quantity');
    }

    public function scopeLabel(): string
    {
        if ($this->discount_scope === 'category') {
            return 'Categoría: ' . ($this->category?->name ?? 'eliminada');
        }

        return self::SCOPES[$this->discount_scope] ?? $this->discount_scope;
    }

    public function durationLabel(): string
    {
        return self::DURATIONS[$this->duration_days] ?? $this->duration_days . ' días';
    }
}
