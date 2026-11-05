<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class CashMovement extends Model
{
    use BelongsToCompany, HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id', 'cash_session_id', 'financial_account_id', 'type', 'amount', 'concept', 'category',
        'movement_date', 'payment_method', 'reference', 'supplier_id', 'personal_id', 'created_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'movement_date' => 'date',
        'deleted_at' => 'datetime',
    ];

    /** ingreso | egreso */
    public const TYPES = [
        'ingreso' => 'Ingreso',
        'egreso'  => 'Gasto',
    ];

    /** Dinero que cambia de lugar dentro del negocio: no es gasto ni ingreso. */
    public const CATEGORY_TRANSFER = 'Traspaso entre cuentas';

    public function account(): BelongsTo
    {
        return $this->belongsTo(FinancialAccount::class, 'financial_account_id');
    }

    public function supplierRef(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    public function personal(): BelongsTo
    {
        return $this->belongsTo(Personal::class);
    }

    /** Excluye los traspasos: lo que queda son gastos e ingresos reales. */
    public function scopeNotTransfer($query)
    {
        return $query->where(fn ($q) => $q->whereNull('category')->orWhere('category', '!=', self::CATEGORY_TRANSFER));
    }

    /** Lo creó otro módulo (venta, cobro, pago, liquidación, compra…): se anula desde allí. */
    public function isGenerated(): bool
    {
        if ($this->category === self::CATEGORY_TRANSFER || ($this->reference && str_starts_with((string) $this->concept, 'Venta '))) {
            return true;
        }

        foreach ([PayablePayment::class, ReceivablePayment::class, CommissionSettlement::class, PurchaseOrder::class, PurchaseReturn::class] as $model) {
            if ($model::withoutGlobalScopes()->where('cash_movement_id', $this->id)->exists()) {
                return true;
            }
        }

        return false;
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(CashSession::class, 'cash_session_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function isIncome(): bool
    {
        return $this->type === 'ingreso';
    }
}
