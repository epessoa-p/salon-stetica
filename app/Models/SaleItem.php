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
        'combo_id', 'service_package_id', 'client_package_id', 'promotion_id', 'uses_session', 'description', 'quantity',
        'returned_quantity', 'unit_price', 'discount', 'membership_discount', 'package_discount', 'promotion_discount', 'total',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'returned_quantity' => 'decimal:2',
        'package_discount' => 'decimal:2',
        'promotion_discount' => 'decimal:2',
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

    public function promotion(): BelongsTo
    {
        return $this->belongsTo(Promotion::class)->withTrashed();
    }

    public function combo(): BelongsTo
    {
        return $this->belongsTo(Combo::class)->withTrashed();
    }

    /** Paquete que se vende en esta línea. */
    public function servicePackage(): BelongsTo
    {
        return $this->belongsTo(ServicePackage::class)->withTrashed();
    }

    /** Paquete del cliente del que se usó la sesión. */
    public function clientPackage(): BelongsTo
    {
        return $this->belongsTo(ClientPackage::class);
    }

    public function returnItems(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(SaleReturnItem::class, 'sale_detail_id');
    }

    public function isPackageSale(): bool
    {
        return (int) $this->service_package_id > 0;
    }

    /** Cantidad que todavía se puede devolver. */
    public function returnableQuantity(): float
    {
        return max(0, round((float) $this->quantity - (float) $this->returned_quantity, 2));
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
