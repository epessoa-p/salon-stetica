<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Cuenta por pagar: deuda con un proveedor por una orden recibida a crédito.
 * Saldo = monto − pagos − lo descontado por devoluciones (notas de crédito).
 */
class Payable extends Model
{
    use BelongsToCompany, HasFactory;

    protected $fillable = ['company_id', 'supplier_id', 'purchase_order_id', 'amount', 'paid_amount', 'adjusted_amount', 'due_date', 'status', 'notes', 'created_by'];

    protected $casts = [
        'amount'          => 'decimal:2',
        'paid_amount'     => 'decimal:2',
        'adjusted_amount' => 'decimal:2',
        'due_date'        => 'date',
    ];

    public function provider(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class, 'purchase_order_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(PayablePayment::class);
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
        return Receivable::STATES[$this->state()][0] ?? $this->status;
    }

    public function stateBadge(): string
    {
        return Receivable::STATES[$this->state()][1] ?? 'bg-secondary';
    }

    public function supplierDisplay(): string
    {
        return $this->provider?->name ?? $this->order?->supplierDisplay() ?? '—';
    }

    public function daysOverdue(): int
    {
        return $this->state() === 'overdue' ? (int) $this->due_date->diffInDays(today()) : 0;
    }
}
