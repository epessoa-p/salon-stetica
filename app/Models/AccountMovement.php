<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Línea del libro de una cuenta. No se borra: anularla la saca del saldo. */
class AccountMovement extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'company_id', 'financial_account_id', 'type', 'direction', 'amount', 'concept', 'category',
        'movement_date', 'reference', 'cash_movement_id', 'counterpart_movement_id', 'voided_at', 'voided_by', 'created_by',
    ];

    protected $casts = [
        'amount'        => 'decimal:2',
        'movement_date' => 'date',
        'voided_at'     => 'datetime',
    ];

    public const TYPES = [
        'apertura'         => 'Saldo inicial',
        'manual'           => 'Manual',
        'aporte'           => 'Manual',
        'ajuste'           => 'Manual',
        'gasto'            => 'Gasto',
        'ingreso'          => 'Ingreso',
        'traspaso_entrada' => 'Transferencia',
        'traspaso_salida'  => 'Transferencia',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(FinancialAccount::class, 'financial_account_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isVoided(): bool
    {
        return $this->voided_at !== null;
    }

    public function isIn(): bool
    {
        return $this->direction === 'in';
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    /** Solo lo manual y las transferencias se anulan desde la cuenta; un gasto, desde su ficha. */
    public function canVoidHere(): bool
    {
        return !$this->isVoided() && !in_array($this->type, ['gasto', 'ingreso'], true);
    }

    public function signedAmount(): float
    {
        return $this->isIn() ? (float) $this->amount : -(float) $this->amount;
    }
}
