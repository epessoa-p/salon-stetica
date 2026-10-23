<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Combo: varios servicios y productos a un precio especial que se cobran en
 * una sola venta. Al cobrar se desarma en sus componentes (stock, comisiones
 * y reportes por servicio) y el precio se reparte según el precio de lista.
 */
class Combo extends Model
{
    use BelongsToCompany, HasFactory, SoftDeletes;

    protected $fillable = ['company_id', 'name', 'description', 'price', 'active', 'sort_order', 'created_by'];

    protected $casts = [
        'price'      => 'decimal:2',
        'active'     => 'boolean',
        'deleted_at' => 'datetime',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(ComboItem::class);
    }

    /** Suma de los precios de lista de los componentes (lo que costaría por separado). */
    public function listPrice(): float
    {
        return round($this->items->sum(fn (ComboItem $i) => $i->listPrice() * (float) $i->quantity), 2);
    }

    /** "Corte, 2× Shampoo" */
    public function summary(): string
    {
        return $this->items->map(fn (ComboItem $i) => ((float) $i->quantity != 1 ? rtrim(rtrim(number_format((float) $i->quantity, 2), '0'), '.') . '× ' : '') . $i->name())->implode(', ');
    }

    public function hasProducts(): bool
    {
        return $this->items->contains(fn (ComboItem $i) => (bool) $i->product_id);
    }
}
