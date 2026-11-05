<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Cuenta de la empresa (caja chica, banco…). Su saldo es la suma de sus movimientos. */
class FinancialAccount extends Model
{
    use BelongsToCompany, SoftDeletes;

    protected $fillable = [
        'company_id', 'branch_id', 'name', 'type', 'bank_name', 'account_number', 'notes', 'active', 'created_by',
    ];

    protected $casts = [
        'active' => 'boolean',
    ];

    public const TYPES = [
        'caja_chica' => 'Caja chica',
        'banco'      => 'Banco',
        'billetera'  => 'Billetera digital',
        'otra'       => 'Otra',
    ];

    public const ICONS = [
        'caja_chica' => 'bi-piggy-bank',
        'banco'      => 'bi-bank',
        'billetera'  => 'bi-phone',
        'otra'       => 'bi-wallet2',
    ];

    public function movements(): HasMany
    {
        return $this->hasMany(AccountMovement::class);
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    public function icon(): string
    {
        return self::ICONS[$this->type] ?? 'bi-wallet2';
    }

    public function balance(): float
    {
        return round((float) $this->movements()->whereNull('voided_at')
            ->selectRaw("COALESCE(SUM(CASE WHEN direction = 'in' THEN amount ELSE -amount END), 0) as saldo")
            ->value('saldo'), 2);
    }

    /** Agrega el saldo como columna `saldo`, para listados sin una consulta por cuenta. */
    public function scopeWithBalance(Builder $query): Builder
    {
        return $query->addSelect(['saldo' => AccountMovement::query()
            ->withoutGlobalScopes()
            ->selectRaw("COALESCE(SUM(CASE WHEN direction = 'in' THEN amount ELSE -amount END), 0)")
            ->whereColumn('financial_account_id', 'financial_accounts.id')
            ->whereNull('voided_at')]);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
