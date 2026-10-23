<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaleItem extends Model
{
    use HasFactory;

    protected $table = 'sale_details';

    protected $fillable = [
        'sale_id', 'product_id', 'service_id', 'personal_id', 'membership_plan_id', 'client_membership_id',
        'uses_session', 'description', 'quantity', 'unit_price', 'discount', 'membership_discount', 'total',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'unit_price' => 'decimal:2',
        'discount' => 'decimal:2',
        'membership_discount' => 'decimal:2',
        'total' => 'decimal:2',
        'uses_session' => 'boolean',
    ];

    /** Plan de membresía que se vende en esta línea. */
    public function membershipPlan(): BelongsTo
    {
        return $this->belongsTo(MembershipPlan::class);
    }

    /** Membresía del cliente que dio el beneficio (descuento o sesión). */
    public function clientMembership(): BelongsTo
    {
        return $this->belongsTo(ClientMembership::class);
    }

    public function isMembershipSale(): bool
    {
        return (int) $this->membership_plan_id > 0;
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class, 'sale_id');
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function specialist(): BelongsTo
    {
        return $this->belongsTo(Personal::class, 'personal_id');
    }

    public function isProduct(): bool
    {
        return (int) $this->product_id > 0;
    }
}
