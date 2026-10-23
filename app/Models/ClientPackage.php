<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Paquete vendido a un cliente. Guarda una copia del paquete (sesiones y lo
 * que vale cada una) para que cambiar el catálogo no altere lo ya vendido.
 * Un cliente puede tener varios a la vez.
 */
class ClientPackage extends Model
{
    use BelongsToCompany, HasFactory;

    protected $fillable = [
        'company_id', 'client_id', 'service_package_id', 'sale_id', 'sale_detail_id',
        'package_name', 'price_paid', 'starts_on', 'expires_on', 'status', 'canceled_at', 'notes', 'created_by',
    ];

    protected $casts = [
        'price_paid'  => 'decimal:2',
        'starts_on'   => 'date',
        'expires_on'  => 'date',
        'canceled_at' => 'datetime',
    ];

    /** Estado visible: clave => [etiqueta, clase de badge]. Agotado y vencido se derivan. */
    public const STATES = [
        'active'    => ['Activo', 'bg-success'],
        'used_up'   => ['Agotado', 'bg-secondary'],
        'expired'   => ['Vencido', 'bg-secondary'],
        'canceled'  => ['Cancelado', 'bg-danger'],
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(ServicePackage::class, 'service_package_id');
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(ClientPackageItem::class);
    }

    public function isExpired(?Carbon $on = null): bool
    {
        return $this->expires_on && $this->expires_on->toDateString() < ($on ?? today())->toDateString();
    }

    /** $remaining: total de sesiones que quedan (lo calcula PackageService). */
    public function state(int $remaining): string
    {
        return match (true) {
            $this->status === 'canceled' => 'canceled',
            $this->isExpired()           => 'expired',
            $remaining <= 0              => 'used_up',
            default                      => 'active',
        };
    }

    public function expiryLabel(): string
    {
        return $this->expires_on ? 'Vence ' . $this->expires_on->format('d/m/Y') : 'Sin vencimiento';
    }
}
