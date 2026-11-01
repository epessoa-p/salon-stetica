<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Traspaso de productos de un almacén a otro (sale de uno y entra al otro en el momento). */
class StockTransfer extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id', 'transfer_number', 'from_warehouse_id', 'to_warehouse_id', 'status',
        'notes', 'transferred_at', 'created_by', 'voided_at', 'voided_by',
    ];

    protected $casts = [
        'transferred_at' => 'datetime',
        'voided_at' => 'datetime',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(StockTransferItem::class);
    }

    public function from(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'from_warehouse_id');
    }

    public function to(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'to_warehouse_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isVoided(): bool
    {
        return $this->status === 'anulada';
    }
}
