<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Comisión devengada: lo que generó una línea de venta concreta al cobrarse.
 *
 * Guarda una copia del tipo y valor de la regla (calc_type / calc_value) para
 * que se pueda explicar aunque la regla cambie o se borre. Una comisión con
 * calc_type = 'reversal' es el negativo de otra ya liquidada cuya venta se anuló.
 */
class Commission extends Model
{
    use BelongsToCompany, HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id', 'personal_id', 'sale_id', 'sale_detail_id', 'rule_id', 'settlement_id',
        'reverses_id', 'base', 'calc_type', 'calc_value', 'accrued_at', 'amount', 'rate',
        'status', 'paid_at', 'paid_by', 'period_start', 'period_end', 'notes', 'created_by',
    ];

    protected $casts = [
        'amount'       => 'decimal:2',
        'rate'         => 'decimal:2',
        'base'         => 'decimal:2',
        'calc_value'   => 'decimal:2',
        'accrued_at'   => 'datetime',
        'paid_at'      => 'datetime',
        'period_start' => 'date',
        'period_end'   => 'date',
        'deleted_at'   => 'datetime',
    ];

    /** pendiente | pagada */
    public const STATUSES = [
        'pendiente' => ['Pendiente', 'bg-warning text-dark'],
        'pagada'    => ['Liquidada', 'bg-success'],
    ];

    public function specialist(): BelongsTo
    {
        return $this->belongsTo(Personal::class, 'personal_id');
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function saleItem(): BelongsTo
    {
        return $this->belongsTo(SaleItem::class, 'sale_detail_id');
    }

    public function rule(): BelongsTo
    {
        return $this->belongsTo(CommissionRule::class, 'rule_id');
    }

    public function settlement(): BelongsTo
    {
        return $this->belongsTo(CommissionSettlement::class, 'settlement_id');
    }

    public function reversed(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reverses_id');
    }

    /**
     * Pendiente = sin liquidar. Se mira el estado (y no solo settlement_id)
     * porque las comisiones del esquema anterior se pagaban sin liquidación.
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pendiente')->whereNull('settlement_id');
    }

    public function isPending(): bool
    {
        return $this->status === 'pendiente' && !$this->settlement_id;
    }

    public function isPaid(): bool
    {
        return !$this->isPending();
    }

    public function isReversal(): bool
    {
        return $this->calc_type === 'reversal';
    }

    public function statusLabel(): string
    {
        if ($this->settlement) {
            return 'Liquidada · ' . $this->settlement->number;
        }

        return self::STATUSES[$this->status][0] ?? $this->status;
    }

    public function statusBadge(): string
    {
        return self::STATUSES[$this->status][1] ?? 'bg-secondary';
    }

    /** Concepto: la descripción de la línea, o lo que se está revirtiendo. */
    public function concept(): string
    {
        $line = $this->saleItem?->description ?: ($this->saleItem?->service?->name ?? 'Línea de venta');

        return $this->isReversal() ? 'Reverso por anulación · ' . $line : $line;
    }

    /** "40 %", "Bs 20 c/u" o "Reverso". */
    public function rateLabel(): string
    {
        if ($this->isReversal()) {
            return 'Reverso';
        }

        return CommissionRule::formatRate($this->calc_type, (float) $this->calc_value);
    }
}
