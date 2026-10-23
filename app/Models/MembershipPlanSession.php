<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Sesiones de un servicio incluidas en un plan de membresía. */
class MembershipPlanSession extends Model
{
    protected $fillable = ['membership_plan_id', 'service_id', 'quantity'];

    protected $casts = ['quantity' => 'integer'];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(MembershipPlan::class, 'membership_plan_id');
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }
}
