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
    ];

    protected $casts = [
        'trial_ends_at' => 'datetime',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'amount' => 'decimal:2',
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
        $graceDays = (int) config('saas.grace_days', 5);

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
}
