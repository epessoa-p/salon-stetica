<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Commission extends Model
{
    use BelongsToCompany, HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id', 'personal_id', 'sale_id', 'sale_detail_id', 'amount', 'rate',
        'status', 'paid_at', 'paid_by', 'period_start', 'period_end', 'notes', 'created_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'rate' => 'decimal:2',
        'paid_at' => 'datetime',
        'period_start' => 'date',
        'period_end' => 'date',
        'deleted_at' => 'datetime',
    ];

    /** pendiente | pagada */
    public const STATUSES = [
        'pendiente' => ['Pendiente', 'bg-warning text-dark'],
        'pagada'    => ['Pagada', 'bg-success'],
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

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status][0] ?? $this->status;
    }

    public function statusBadge(): string
    {
        return self::STATUSES[$this->status][1] ?? 'bg-secondary';
    }

    public function isPaid(): bool
    {
        return $this->status === 'pagada';
    }
}
