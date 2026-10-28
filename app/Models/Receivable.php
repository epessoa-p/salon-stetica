<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Cuenta por cobrar: lo que quedó pendiente de una venta a crédito.
 * Saldo = monto − abonos − lo descontado por devoluciones.
 */
class Receivable extends Model
{
    use BelongsToCompany, HasFactory;

    protected $fillable = ['company_id', 'client_id', 'sale_id', 'amount', 'paid_amount', 'adjusted_amount', 'due_date', 'status', 'notes', 'created_by'];

    protected $casts = [
        'amount'          => 'decimal:2',
        'paid_amount'     => 'decimal:2',
        'adjusted_amount' => 'decimal:2',
        'due_date'        => 'date',
    ];

    /** Estado visible: clave => [etiqueta, clase de badge]. "Vencida" se deriva de la fecha. */
    public const STATES = [
        'pending'  => ['Pendiente', 'bg-warning text-dark'],
        'overdue'  => ['Vencida', 'bg-danger'],
        'paid'     => ['Pagada', 'bg-success'],
        'canceled' => ['Cancelada', 'bg-secondary'],
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(ReceivablePayment::class);
    }

    public function balance(): float
    {
        return max(0, round((float) $this->amount - (float) $this->paid_amount - (float) $this->adjusted_amount, 2));
    }

    public function state(): string
    {
        if ($this->status === 'pending' && $this->due_date->toDateString() < today()->toDateString()) {
            return 'overdue';
        }

        return $this->status;
    }

    public function stateLabel(): string
    {
        return self::STATES[$this->state()][0] ?? $this->status;
    }

    public function stateBadge(): string
    {
        return self::STATES[$this->state()][1] ?? 'bg-secondary';
    }

    /** Días de atraso (0 si no venció). */
    public function daysOverdue(): int
    {
        return $this->state() === 'overdue' ? (int) $this->due_date->diffInDays(today()) : 0;
    }
}
