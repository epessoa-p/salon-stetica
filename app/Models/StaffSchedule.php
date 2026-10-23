<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Bloque del horario semanal de un profesional (puede haber varios por día). */
class StaffSchedule extends Model
{
    use BelongsToCompany, HasFactory;

    protected $fillable = ['company_id', 'personal_id', 'weekday', 'starts_time', 'ends_time'];

    protected $casts = ['weekday' => 'integer'];

    /** 1 = lunes … 7 = domingo (ISO-8601, igual que Carbon::dayOfWeekIso). */
    public const WEEKDAYS = [
        1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles', 4 => 'Jueves',
        5 => 'Viernes', 6 => 'Sábado', 7 => 'Domingo',
    ];

    public function specialist(): BelongsTo
    {
        return $this->belongsTo(Personal::class, 'personal_id');
    }

    /** "09:00–13:00" */
    public function label(): string
    {
        return substr($this->starts_time, 0, 5) . '–' . substr($this->ends_time, 0, 5);
    }
}
