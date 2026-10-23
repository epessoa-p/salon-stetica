<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Sesiones incluidas en una membresía vendida. Las usadas no se guardan: se
 * cuentan desde las líneas de venta no anuladas (ver MembershipService).
 */
class ClientMembershipSession extends Model
{
    protected $fillable = ['client_membership_id', 'service_id', 'included'];

    protected $casts = ['included' => 'integer'];

    public function membership(): BelongsTo
    {
        return $this->belongsTo(ClientMembership::class, 'client_membership_id');
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }
}
