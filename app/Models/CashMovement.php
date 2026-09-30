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
        'company_id', 'cash_session_id', 'type', 'amount', 'concept', 'category',
        'movement_date', 'payment_method', 'reference', 'created_by',
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
