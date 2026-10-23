<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Sesiones de un servicio dentro de un paquete vendido. Las usadas no se
 * guardan: se cuentan desde las líneas de venta (ver PackageService).
 */
class ClientPackageItem extends Model
{
    protected $fillable = ['client_package_id', 'service_id', 'included', 'unit_value'];

    protected $casts = [
        'included'   => 'integer',
        'unit_value' => 'decimal:2',
    ];

    public function clientPackage(): BelongsTo
    {
        return $this->belongsTo(ClientPackage::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class)->withoutGlobalScopes();
    }
}
