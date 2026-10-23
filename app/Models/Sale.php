<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Sale extends Model
{
    use BelongsToCompany, HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id', 'branch_id', 'client_id', 'personal_id', 'cash_session_id', 'sale_number',
        'client_name', 'client_phone', 'client_document', 'sale_date',
        'subtotal', 'tax', 'discount', 'membership_discount', 'loyalty_points_redeemed', 'loyalty_discount',
        'loyalty_points_earned', 'total', 'tip', 'tip_settlement_id', 'payment_method', 'status',
        'notes', 'created_by',
    ];

    protected $casts = [
        'sale_date' => 'datetime',
        'subtotal' => 'decimal:2',
        'tax' => 'decimal:2',
        'discount' => 'decimal:2',
        'membership_discount' => 'decimal:2',
        'loyalty_discount' => 'decimal:2',
        'loyalty_points_redeemed' => 'integer',
        'loyalty_points_earned' => 'integer',
        'total' => 'decimal:2',
        'tip' => 'decimal:2',
        'deleted_at' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(CashSession::class, 'cash_session_id');
    }

    /** Profesional principal: recibe la propina y lo heredan las líneas sin profesional. */
    public function specialist(): BelongsTo
    {
        return $this->belongsTo(Personal::class, 'personal_id');
    }

    public function tipSettlement(): BelongsTo
    {
        return $this->belongsTo(CommissionSettlement::class, 'tip_settlement_id');
    }

    public function commissions(): HasMany
    {
        return $this->hasMany(Commission::class);
    }

    public function isVoided(): bool
    {
        return $this->status === 'anulada';
    }

    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class, 'sale_id');
    }

    public function clientDisplay(): string
    {
        return $this->client?->full_name ?? ($this->client_name ?: 'Cliente ocasional');
    }
}
