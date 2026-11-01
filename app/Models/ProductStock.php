<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Existencia de un producto en un almacén. La suma de todos es products.current_stock. */
class ProductStock extends Model
{
    use BelongsToCompany;

    protected $fillable = ['company_id', 'product_id', 'warehouse_id', 'quantity'];

    protected $casts = ['quantity' => 'decimal:2'];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }
}
