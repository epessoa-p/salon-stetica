<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Abono a una cuenta por cobrar. Anularlo es un borrado lógico. */
class ReceivablePayment extends Model
{
    use BelongsToCompany, HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id', 'receivable_id', 'client_id', 'receipt_number', 'amount', 'payment_method',
        'cash_session_id', 'cash_movement_id', 'paid_at', 'notes', 'created_by',
    ];

    protected $casts = [
        'amount'     => 'decimal:2',
        'paid_at'    => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function receivable(): BelongsTo
    {
        return $this->belongsTo(Receivable::class);
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
}
