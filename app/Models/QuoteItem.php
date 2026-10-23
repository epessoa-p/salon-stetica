<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Línea de una cotización: lo mismo que una línea del carrito del POS. */
class QuoteItem extends Model
{
    protected $fillable = [
        'quote_id', 'kind', 'service_id', 'product_id', 'combo_id', 'service_package_id', 'membership_plan_id',
        'personal_id', 'description', 'quantity', 'unit_price', 'discount', 'total',
    ];

    protected $casts = [
        'quantity'   => 'decimal:2',
        'unit_price' => 'decimal:2',
        'discount'   => 'decimal:2',
        'total'      => 'decimal:2',
    ];

    public const KIND_LABELS = [
        'service'    => 'Servicio',
        'product'    => 'Producto',
        'combo'      => 'Combo',
        'package'    => 'Paquete',
        'membership' => 'Membresía',
        'custom'     => 'Otro',
    ];

    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
    }

    public function specialist(): BelongsTo
    {
        return $this->belongsTo(Personal::class, 'personal_id');
    }

    public function kindLabel(): string
    {
        return self::KIND_LABELS[$this->kind] ?? $this->kind;
    }

    /** Clave del catálogo del POS ("service:12") para recargar el carrito. */
    public function catalogKey(): ?string
    {
        $id = match ($this->kind) {
            'service'    => $this->service_id,
            'product'    => $this->product_id,
            'combo'      => $this->combo_id,
            'package'    => $this->service_package_id,
            'membership' => $this->membership_plan_id,
            default      => null,
        };

        return $id ? $this->kind . ':' . $id : null;
    }
}
