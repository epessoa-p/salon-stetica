<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Membresía vendida a un cliente. Guarda una copia del plan (precio, %,
 * alcance, sesiones) para que cambiar el plan no altere lo ya vendido.
 */
class ClientMembership extends Model
{
    use BelongsToCompany, HasFactory;

    protected $fillable = [
        'company_id', 'client_id', 'membership_plan_id', 'sale_id', 'sale_detail_id',
        'plan_name', 'price_paid', 'discount_percent', 'discount_scope', 'service_category_id',
        'session_unit_value', 'starts_on', 'ends_on', 'status', 'canceled_at', 'notes', 'created_by',
    ];

    protected $casts = [
        'price_paid'         => 'decimal:2',
        'discount_percent'   => 'decimal:2',
        'session_unit_value' => 'decimal:2',
        'starts_on'          => 'date',
        'ends_on'            => 'date',
        'canceled_at'        => 'datetime',
    ];

    /** Estado visible: clave => [etiqueta, clase de badge]. */
    public const STATES = [
        'active'    => ['Activa', 'bg-success'],
        'scheduled' => ['Programada', 'bg-info text-dark'],
        'expired'   => ['Vencida', 'bg-secondary'],
        'canceled'  => ['Cancelada', 'bg-danger'],
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(MembershipPlan::class, 'membership_plan_id');
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ServiceCategory::class, 'service_category_id');
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(ClientMembershipSession::class);
    }

    /** "Vencida" no se guarda: se deriva de la fecha de fin. */
    public function state(?Carbon $on = null): string
    {
        $day = ($on ?? today())->toDateString();

        if ($this->status === 'canceled') {
            return 'canceled';
        }
        if ($this->starts_on->toDateString() > $day) {
            return 'scheduled';
        }

        return $this->ends_on->toDateString() < $day ? 'expired' : 'active';
    }

    public function isActiveOn(?Carbon $on = null): bool
    {
        return $this->state($on) === 'active';
    }

    public function stateLabel(): string
    {
        return self::STATES[$this->state()][0];
    }

    public function stateBadge(): string
    {
        return self::STATES[$this->state()][1];
    }

    public function daysLeft(): int
    {
        return max(0, (int) today()->diffInDays($this->ends_on, false));
    }

    /**
     * ¿El descuento cubre esta línea? $kind: service | product.
     * Para servicios se pasa su categoría, por si el alcance es una categoría.
     */
    public function covers(string $kind, ?int $categoryId = null): bool
    {
        return match ($this->discount_scope) {
            'services' => $kind === 'service',
            'products' => $kind === 'product',
            'all'      => in_array($kind, ['service', 'product'], true),
            'category' => $kind === 'service' && $categoryId && (int) $categoryId === (int) $this->service_category_id,
            default    => false,
        };
    }

    public function scopeLabel(): string
    {
        if ($this->discount_scope === 'category') {
            return 'Categoría: ' . ($this->category?->name ?? 'eliminada');
        }

        return MembershipPlan::SCOPES[$this->discount_scope] ?? $this->discount_scope;
    }
}
