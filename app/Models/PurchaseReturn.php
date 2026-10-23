<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Devolución de mercadería a un proveedor, sobre una orden de compra recibida. */
class PurchaseReturn extends Model
{
    use BelongsToCompany, HasFactory;

    protected $fillable = [
        'company_id', 'supplier_id', 'purchase_order_id', 'return_number', 'return_date', 'reason',
        'refund_method', 'cash_movement_id', 'total', 'notes', 'created_by',
    ];

    protected $casts = [
        'return_date' => 'datetime',
        'total'       => 'decimal:2',
    ];

    public const REFUND_METHODS = [
        'credit' => 'Nota de crédito del proveedor',
        'cash'   => 'El proveedor devolvió el dinero',
    ];

    public function provider(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class, 'purchase_order_id');
    }

    public function cashMovement(): BelongsTo
    {
        return $this->belongsTo(CashMovement::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseReturnItem::class);
    }

    public function refundLabel(): string
    {
        return self::REFUND_METHODS[$this->refund_method] ?? $this->refund_method;
    }
}
