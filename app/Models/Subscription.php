<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

class Subscription extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id', 'plan_id', 'status', 'trial_ends_at', 'starts_at', 'ends_at',
        'amount', 'notes', 'created_by',
        'max_users_override', 'max_branches_override', 'max_cajas_override',
        'max_personal_override', 'max_products_override', 'features_override', 'grace_days',
    ];

    protected $casts = [
        'trial_ends_at' => 'datetime',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'amount' => 'decimal:2',
        'features_override' => 'array',
        'deleted_at' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SubscriptionPayment::class);
    }

    /**
     * Fecha efectiva de vigencia: la de pago (ends_at) o, si sigue en prueba, la de prueba.
     */
    public function effectiveEndsAt(): ?Carbon
    {
        return $this->ends_at ?? $this->trial_ends_at;
    }

    /**
     * Estado calculado dinámicamente a partir de fechas + periodo de gracia.
     * Devuelve: trial | active | grace | expired | canceled
     */
    public function computedStatus(): string
    {
        if ($this->status === 'canceled') {
            return 'canceled';
        }

        $now = now();
        $graceDays = $this->graceDays();

        // En prueba: aún no hay pago y la prueba no ha vencido.
        if (!$this->ends_at && $this->trial_ends_at && $now->lte($this->trial_ends_at)) {
            return 'trial';
        }

        $end = $this->effectiveEndsAt();

        if (!$end) {
            return 'expired';
        }

        if ($now->lte($end)) {
            return 'active';
        }

        if ($now->lte($end->copy()->addDays($graceDays))) {
            return 'grace';
        }

        return 'expired';
    }

    /**
     * Días restantes hasta el vencimiento efectivo (negativo si ya venció).
     */
    public function daysLeft(): ?int
    {
        $end = $this->effectiveEndsAt();
        return $end ? now()->startOfDay()->diffInDays($end->copy()->startOfDay(), false) : null;
    }

    /** Días de gracia efectivos (override de la suscripción o config global). */
    public function graceDays(): int
    {
        return (int) ($this->grace_days ?? config('saas.grace_days', 5));
    }

    /** Límite efectivo de un recurso: override de la empresa ?? límite del plan. Null = ilimitado. */
    public function effectiveLimitFor(string $key): ?int
    {
        $override = match ($key) {
            'users'    => $this->max_users_override,
            'branches' => $this->max_branches_override,
            'cajas'    => $this->max_cajas_override,
            'personal' => $this->max_personal_override,
            'products' => $this->max_products_override,
            default    => null,
        };

        return $override ?? $this->plan?->limitFor($key);
    }

    /** Módulos efectivos: override de la empresa ?? features del plan. Null = sin restricción. */
    public function effectiveFeatures(): ?array
    {
        return $this->features_override ?? $this->plan?->features;
    }

    /** Uso normal (leer y escribir): prueba o periodo pagado vigente. */
    public function allowsWrite(): bool
    {
        return in_array($this->computedStatus(), ['trial', 'active'], true);
    }

    /** Al menos entrar y consultar: incluye periodo de gracia. */
    public function allowsRead(): bool
    {
        return in_array($this->computedStatus(), ['trial', 'active', 'grace'], true);
    }
}
