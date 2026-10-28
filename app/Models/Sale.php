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
        'subtotal', 'tax', 'discount', 'membership_discount', 'package_discount', 'loyalty_points_redeemed', 'loyalty_discount',
        'loyalty_points_earned', 'total', 'tip', 'credit_applied', 'on_credit', 'returned_total', 'quote_id', 'coupon_id', 'coupon_discount', 'promotion_discount', 'tip_settlement_id', 'payment_method', 'status',
        'notes', 'created_by',
    ];

    protected $casts = [
        'sale_date' => 'datetime',
        'subtotal' => 'decimal:2',
        'tax' => 'decimal:2',
        'discount' => 'decimal:2',
        'membership_discount' => 'decimal:2',
        'package_discount' => 'decimal:2',
        'credit_applied' => 'decimal:2',
        'on_credit' => 'boolean',
        'coupon_discount' => 'decimal:2',
        'promotion_discount' => 'decimal:2',
        'returned_total' => 'decimal:2',
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

    public function returns(): HasMany
    {
        return $this->hasMany(SaleReturn::class);
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class)->withTrashed();
    }

    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
    }

    /** Cuenta por cobrar de una venta a crédito. */
    public function receivable(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Receivable::class);
    }

    /**
     * Lo que entró en dinero por la venta (sin propina): el total menos lo
     * pagado con saldo a favor y, si fue a crédito, menos lo que quedó
     * pendiente más lo que ya se abonó.
     */
    public function paidInMoney(): float
    {
        $money = (float) $this->total - (float) $this->credit_applied;
        if ($this->on_credit && ($r = $this->receivable)) {
            $money -= (float) $r->amount - (float) $r->paid_amount;
        }

        return max(0, round($money, 2));
    }

    public function hasReturns(): bool
    {
        return (float) $this->returned_total > 0 || $this->returns()->exists();
    }

    /** Total neto de devoluciones. */
    public function netTotal(): float
    {
        return round((float) $this->total - (float) $this->returned_total, 2);
    }

    /** none | partial | full según lo devuelto. */
    public function returnState(): string
    {
        if ((float) $this->returned_total <= 0 && !$this->items->contains(fn ($i) => (float) $i->returned_quantity > 0)) {
            return 'none';
        }

        return $this->items->every(fn ($i) => (float) $i->returned_quantity >= (float) $i->quantity) ? 'full' : 'partial';
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
