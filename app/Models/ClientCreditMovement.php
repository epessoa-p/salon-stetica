<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Movimiento de saldo a favor. El saldo de un cliente es la suma de `amount`. */
class ClientCreditMovement extends Model
{
    use BelongsToCompany, HasFactory;

    protected $fillable = ['company_id', 'client_id', 'sale_id', 'sale_return_id', 'type', 'amount', 'notes', 'created_by'];

    protected $casts = ['amount' => 'decimal:2'];

    /** Tipos: código => [etiqueta, clase de badge]. */
    public const TYPES = [
        'return'  => ['Devolución', 'bg-success'],
        'use'     => ['Usado en venta', 'bg-primary'],
        'reverse' => ['Reverso por anulación', 'bg-secondary'],
        'adjust'  => ['Ajuste', 'bg-warning text-dark'],
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function saleReturn(): BelongsTo
    {
        return $this->belongsTo(SaleReturn::class);
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type][0] ?? $this->type;
    }

    public function typeBadge(): string
    {
        return self::TYPES[$this->type][1] ?? 'bg-secondary';
    }
}
