<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Cotización (presupuesto). Se arma en el punto de venta y se convierte en
 * venta: mientras está vigente se respetan sus precios; vencida, se cobran
 * los precios actuales.
 */
class Quote extends Model
{
    use BelongsToCompany, HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id', 'branch_id', 'client_id', 'client_name', 'personal_id', 'quote_number', 'valid_until',
        'status', 'subtotal', 'discount', 'global_discount', 'tax', 'total', 'notes', 'sale_id', 'converted_at', 'created_by',
    ];

    protected $casts = [
        'valid_until'     => 'date',
        'converted_at'    => 'datetime',
        'subtotal'        => 'decimal:2',
        'discount'        => 'decimal:2',
        'global_discount' => 'decimal:2',
        'tax'             => 'decimal:2',
        'total'           => 'decimal:2',
        'deleted_at'      => 'datetime',
    ];

    /** Estado visible: clave => [etiqueta, clase de badge]. "Vencida" se deriva de la fecha. */
    public const STATES = [
        'pendiente'  => ['Vigente', 'bg-primary'],
        'vencida'    => ['Vencida', 'bg-secondary'],
        'convertida' => ['Convertida en venta', 'bg-success'],
        'anulada'    => ['Anulada', 'bg-danger'],
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function specialist(): BelongsTo
    {
        return $this->belongsTo(Personal::class, 'personal_id');
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(QuoteItem::class);
    }

    public function state(): string
    {
        if ($this->status === 'pendiente' && $this->valid_until->toDateString() < today()->toDateString()) {
            return 'vencida';
        }

        return $this->status;
    }

    public function isValid(): bool
    {
        return $this->state() === 'pendiente';
    }

    /** Se puede llevar al punto de venta mientras no esté convertida ni anulada. */
    public function canConvert(): bool
    {
        return in_array($this->state(), ['pendiente', 'vencida'], true);
    }

    public function stateLabel(): string
    {
        return self::STATES[$this->state()][0] ?? $this->status;
    }

    public function stateBadge(): string
    {
        return self::STATES[$this->state()][1] ?? 'bg-secondary';
    }

    public function clientDisplay(): string
    {
        return $this->client?->full_name ?? ($this->client_name ?: 'Cliente sin registrar');
    }
}
