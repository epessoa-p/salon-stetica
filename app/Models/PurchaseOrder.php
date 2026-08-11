<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseOrder extends Model
{
    use HasFactory, SoftDeletes;

    // Reutiliza la tabla heredada `entries` como órdenes de compra.
    protected $table = 'entries';

    protected $fillable = [
        'company_id', 'supplier_id', 'warehouse_id', 'entry_number', 'supplier',
        'entry_date', 'notes', 'status', 'total', 'created_by',
    ];

    protected $casts = [
        'entry_date' => 'datetime',
        'total' => 'decimal:2',
        'deleted_at' => 'datetime',
    ];

    /** pendiente | recibida | anulada */
    public const STATUSES = [
        'pendiente' => ['Pendiente', 'bg-warning text-dark'],
        'recibida'  => ['Recibida', 'bg-success'],
        'anulada'   => ['Anulada', 'bg-danger'],
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    // Se llama "provider" para no colisionar con la columna string `supplier`.
    public function provider(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class, 'entry_id');
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status][0] ?? $this->status;
    }

    public function statusBadge(): string
    {
        return self::STATUSES[$this->status][1] ?? 'bg-secondary';
    }

    public function supplierDisplay(): string
    {
        return $this->provider?->name ?? ($this->getAttribute('supplier') ?: '—');
    }
}
