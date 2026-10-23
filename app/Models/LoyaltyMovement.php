<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Movimiento de puntos. El saldo de un cliente es la suma de `points`. */
class LoyaltyMovement extends Model
{
    use BelongsToCompany, HasFactory;

    protected $fillable = [
        'company_id', 'client_id', 'sale_id', 'type', 'points', 'amount', 'notes', 'created_by',
    ];

    protected $casts = [
        'points' => 'integer',
        'amount' => 'decimal:2',
    ];

    /** Tipos: código => [etiqueta, clase de badge]. */
    public const TYPES = [
        'earn'    => ['Ganados', 'bg-success'],
        'redeem'  => ['Canjeados', 'bg-primary'],
        'reverse' => ['Reverso por anulación', 'bg-warning text-dark'],
        'expire'  => ['Vencidos', 'bg-secondary'],
        'adjust'  => ['Ajuste manual', 'bg-info text-dark'],
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
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
