<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Plan extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name', 'slug', 'description', 'price', 'currency', 'billing_days', 'trial_days',
        'max_branches', 'max_personal', 'max_cajas', 'max_users', 'features', 'active', 'sort_order',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'features' => 'array',
        'active' => 'boolean',
        'deleted_at' => 'datetime',
    ];

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    /**
     * Límite de un recurso ('branches', 'personal', 'cajas', 'users'). Null = ilimitado.
     */
    public function limitFor(string $resource): ?int
    {
        return match ($resource) {
            'branches' => $this->max_branches,
            'personal' => $this->max_personal,
            'cajas'    => $this->max_cajas,
            'users'    => $this->max_users,
            default    => null,
        };
    }
}
