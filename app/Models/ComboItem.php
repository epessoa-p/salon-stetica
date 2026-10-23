<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Componente de un combo: un servicio o un producto con su cantidad. */
class ComboItem extends Model
{
    protected $fillable = ['combo_id', 'service_id', 'product_id', 'quantity'];

    protected $casts = ['quantity' => 'decimal:2'];

    public function combo(): BelongsTo
    {
        return $this->belongsTo(Combo::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class)->withoutGlobalScopes();
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withoutGlobalScopes();
    }

    public function kind(): string
    {
        return $this->product_id ? 'product' : 'service';
    }

    public function name(): string
    {
        return ($this->product_id ? $this->product?->name : $this->service?->name) ?? 'Eliminado';
    }

    public function listPrice(): float
    {
        return (float) (($this->product_id ? $this->product?->price : $this->service?->price) ?? 0);
    }
}
