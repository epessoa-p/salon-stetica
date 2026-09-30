<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class InventoryMovement extends Model
{
    use BelongsToCompany, HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id', 'warehouse_id', 'branch_id', 'product_id', 'user_id',
        'type', 'quantity', 'unit_cost', 'reference', 'notes', 'movement_date',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'unit_cost' => 'decimal:2',
        'movement_date' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /** entrada | salida | ajuste */
    public const TYPES = [
        'entrada' => 'Entrada',
        'salida'  => 'Salida',
        'ajuste'  => 'Ajuste',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
