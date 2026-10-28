<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/** Plantilla de mensaje de WhatsApp con variables como {nombre} o {hora}. */
class MessageTemplate extends Model
{
    use BelongsToCompany, HasFactory;

    protected $fillable = ['company_id', 'type', 'name', 'body', 'is_default', 'active', 'created_by'];

    protected $casts = [
        'is_default' => 'boolean',
        'active'     => 'boolean',
    ];

    public const TYPES = [
        'reminder' => ['Recordatorio de cita', 'bi-bell'],
        'birthday' => ['Cumpleaños', 'bi-cake2'],
        'campaign' => ['Campaña', 'bi-broadcast'],
        'general'  => ['General', 'bi-chat-dots'],
    ];

    /** Variables disponibles: {variable} => descripción. */
    public const VARIABLES = [
        'nombre'      => 'Primer nombre del cliente',
        'cliente'     => 'Nombre completo del cliente',
        'salon'       => 'Nombre del salón',
        'fecha'       => 'Fecha de la cita (ej. «martes 28 de octubre»)',
        'hora'        => 'Hora de la cita',
        'servicio'    => 'Servicio de la cita',
        'profesional' => 'Profesional de la cita',
        'cupon'       => 'Código del cupón elegido',
        'descuento'   => 'Descuento del cupón (ej. «15 %»)',
    ];

    public function typeLabel(): string
    {
        return self::TYPES[$this->type][0] ?? $this->type;
    }
}
