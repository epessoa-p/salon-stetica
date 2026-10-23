<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Una entrada/salida. Con turno partido hay varios registros en el día; la
 * tardanza se calcula solo en la primera entrada del día.
 */
class Attendance extends Model
{
    use BelongsToCompany, HasFactory;

    protected $fillable = [
        'company_id', 'personal_id', 'work_date', 'check_in', 'check_out', 'in_source', 'out_source',
        'in_marked_by', 'out_marked_by', 'scheduled_start', 'late_minutes', 'worked_minutes',
        'notes', 'edited_by', 'edit_reason',
    ];

    protected $casts = [
        'work_date'      => 'date',
        'check_in'       => 'datetime',
        'check_out'      => 'datetime',
        'late_minutes'   => 'integer',
        'worked_minutes' => 'integer',
    ];

    public function specialist(): BelongsTo
    {
        return $this->belongsTo(Personal::class, 'personal_id');
    }

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'edited_by');
    }

    public function isOpen(): bool
    {
        return $this->check_out === null;
    }

    /** "8 h 15 min" */
    public static function duration(?int $minutes): string
    {
        if ($minutes === null) {
            return '—';
        }

        $h = intdiv($minutes, 60);
        $m = $minutes % 60;

        return $h ? ($m ? "{$h} h {$m} min" : "{$h} h") : "{$m} min";
    }
}
