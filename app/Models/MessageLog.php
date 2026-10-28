<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Mensaje enviado a un cliente: queda quién lo contactó y cuándo. */
class MessageLog extends Model
{
    use BelongsToCompany;

    protected $fillable = ['company_id', 'client_id', 'type', 'channel', 'appointment_id', 'campaign_id', 'phone', 'body', 'sent_by', 'sent_at'];

    protected $casts = ['sent_at' => 'datetime'];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by');
    }
}
