<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Cupón de descuento que el cliente presenta con un código en el punto de venta. */
class Coupon extends Model
{
    use BelongsToCompany, HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id', 'client_id', 'code', 'name', 'discount_type', 'value', 'applies_to', 'min_purchase', 'max_discount',
        'starts_on', 'ends_on', 'max_uses', 'max_uses_per_client', 'active', 'notes', 'created_by',
    ];

    protected $casts = [
        'value'               => 'decimal:2',
        'min_purchase'        => 'decimal:2',
        'max_discount'        => 'decimal:2',
        'starts_on'           => 'date',
        'ends_on'             => 'date',
        'max_uses'            => 'integer',
        'max_uses_per_client' => 'integer',
        'active'              => 'boolean',
        'deleted_at'          => 'datetime',
    ];

    public const APPLIES = [
        'all'      => 'Toda la venta',
        'services' => 'Solo servicios',
        'products' => 'Solo productos',
    ];

    public const STATES = [
        'active'    => ['Vigente', 'bg-success'],
        'scheduled' => ['Programado', 'bg-info text-dark'],
        'expired'   => ['Vencido', 'bg-secondary'],
        'inactive'  => ['Inactivo', 'bg-secondary'],
        'used_up'   => ['Agotado', 'bg-warning text-dark'],
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /** Usos: ventas no anuladas que lo aplicaron (opcionalmente de un cliente). */
    public function uses(?int $clientId = null): int
    {
        return Sale::withoutGlobalScopes()->where('coupon_id', $this->id)->where('status', '!=', 'anulada')
            ->when($clientId, fn ($q) => $q->where('client_id', $clientId))->count();
    }

    /** «15 %» o «Bs 20.00» */
    public function valueLabel(?string $currency = null): string
    {
        return $this->discount_type === 'percent'
            ? rtrim(rtrim(number_format((float) $this->value, 2), '0'), '.') . ' %'
            : ($currency ?? 'Bs') . ' ' . number_format((float) $this->value, 2);
    }

    public function state(?int $uses = null): string
    {
        $today = today()->toDateString();

        return match (true) {
            !$this->active                                                              => 'inactive',
            $this->starts_on && $this->starts_on->toDateString() > $today               => 'scheduled',
            $this->ends_on && $this->ends_on->toDateString() < $today                   => 'expired',
            $this->max_uses !== null && ($uses ?? $this->uses()) >= $this->max_uses     => 'used_up',
            default                                                                     => 'active',
        };
    }
}
