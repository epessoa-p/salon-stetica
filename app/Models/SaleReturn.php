<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Devolución de un cliente sobre una venta (parcial o total). */
class SaleReturn extends Model
{
    use BelongsToCompany, HasFactory;

    protected $fillable = [
        'company_id', 'sale_id', 'client_id', 'return_number', 'return_date', 'reason',
        'refund_method', 'payment_method', 'cash_session_id', 'total', 'points_removed', 'points_returned',
        'notes', 'created_by',
    ];

    protected $casts = [
        'return_date'     => 'datetime',
        'total'           => 'decimal:2',
        'points_removed'  => 'integer',
        'points_returned' => 'integer',
    ];

    public const REFUND_METHODS = [
        'cash'   => ['Dinero', 'bi-cash-coin'],
        'credit' => ['Saldo a favor', 'bi-wallet2'],
    ];

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(CashSession::class, 'cash_session_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(SaleReturnItem::class);
    }

    public function refundLabel(): string
    {
        return self::REFUND_METHODS[$this->refund_method][0] ?? $this->refund_method;
    }

    public function refundIcon(): string
    {
        return self::REFUND_METHODS[$this->refund_method][1] ?? 'bi-arrow-return-left';
    }
}
