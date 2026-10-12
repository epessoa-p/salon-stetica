<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Liquidación: el pago al profesional de sus comisiones pendientes de un
 * periodo (y opcionalmente sus propinas). Los totales se guardan al pagar y
 * no se recalculan nunca.
 */
class CommissionSettlement extends Model
{
    use BelongsToCompany, HasFactory;

    protected $fillable = [
        'company_id', 'personal_id', 'number', 'period_start', 'period_end',
        'commissions_count', 'commissions_total', 'tips_total', 'total', 'include_tips',
        'paid_at', 'paid_by', 'cash_session_id', 'cash_movement_id', 'notes',
    ];

    protected $casts = [
        'period_start'      => 'date',
        'period_end'        => 'date',
        'commissions_count' => 'integer',
        'commissions_total' => 'decimal:2',
        'tips_total'        => 'decimal:2',
        'total'             => 'decimal:2',
        'include_tips'      => 'boolean',
        'paid_at'           => 'datetime',
    ];

    public function specialist(): BelongsTo
    {
        return $this->belongsTo(Personal::class, 'personal_id');
    }

    public function payer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by');
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(CashSession::class, 'cash_session_id');
    }

    public function commissions(): HasMany
    {
        return $this->hasMany(Commission::class, 'settlement_id');
    }

    /** Ventas cuya propina se pagó en esta liquidación. */
    public function tipSales(): HasMany
    {
        return $this->hasMany(Sale::class, 'tip_settlement_id');
    }

    /** Siguiente número correlativo por empresa: LIQ-0001, LIQ-0002… */
    public static function nextNumber(int $companyId): string
    {
        $last = static::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->lockForUpdate()
            ->orderByDesc('id')
            ->value('number');

        $next = $last ? ((int) preg_replace('/\D/', '', $last)) + 1 : 1;

        return 'LIQ-' . str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }
}
