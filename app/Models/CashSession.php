<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CashSession extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'cash_register_id', 'personal_id', 'opening_amount', 'closing_amount',
        'expected_amount', 'difference', 'status', 'opened_at', 'closed_at',
        'notes', 'opened_by', 'closed_by',
    ];

    protected $casts = [
        'opening_amount' => 'decimal:2',
        'closing_amount' => 'decimal:2',
        'expected_amount' => 'decimal:2',
        'difference' => 'decimal:2',
        'opened_at' => 'datetime',
        'closed_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function register(): BelongsTo
    {
        // La "Caja" (cash_registers) del sistema.
        return $this->belongsTo(Caja::class, 'cash_register_id');
    }

    public function movements(): HasMany
    {
        return $this->hasMany(CashMovement::class);
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    public function isOpen(): bool
    {
        return $this->status === 'open';
    }

    /** Total de ingresos y egresos registrados en la sesión. */
    public function movementsTotal(): float
    {
        $in = (float) $this->movements()->where('type', 'ingreso')->sum('amount');
        $out = (float) $this->movements()->where('type', 'egreso')->sum('amount');
        return $in - $out;
    }

    /** Saldo esperado = apertura + ingresos - egresos. */
    public function expectedBalance(): float
    {
        return (float) $this->opening_amount + $this->movementsTotal();
    }
}
