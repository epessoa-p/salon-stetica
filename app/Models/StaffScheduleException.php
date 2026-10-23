<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Excepción al horario semanal:
 *  - off:    no trabaja entre date_from y date_to (libre, vacaciones, permiso).
 *  - custom: ese día trabaja de starts_time a ends_time (reemplaza al semanal).
 */
class StaffScheduleException extends Model
{
    use BelongsToCompany, HasFactory;

    protected $fillable = [
        'company_id', 'personal_id', 'type', 'date_from', 'date_to',
        'starts_time', 'ends_time', 'reason', 'created_by',
    ];

    protected $casts = [
        'date_from' => 'date',
        'date_to'   => 'date',
    ];

    public const TYPES = [
        'off'    => 'No trabaja',
        'custom' => 'Horario especial',
    ];

    /** Motivos sugeridos para "No trabaja". */
    public const REASONS = ['Día libre', 'Vacaciones', 'Permiso', 'Enfermedad', 'Capacitación', 'Feriado'];

    public function specialist(): BelongsTo
    {
        return $this->belongsTo(Personal::class, 'personal_id');
    }

    public function rangeLabel(): string
    {
        return $this->date_from->equalTo($this->date_to)
            ? $this->date_from->format('d/m/Y')
            : $this->date_from->format('d/m/Y') . ' – ' . $this->date_to->format('d/m/Y');
    }

    public function timeLabel(): ?string
    {
        return $this->type === 'custom'
            ? substr($this->starts_time, 0, 5) . '–' . substr($this->ends_time, 0, 5)
            : null;
    }
}
