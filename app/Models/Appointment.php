<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Appointment extends Model
{
    use BelongsToCompany, HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id', 'branch_id', 'client_id', 'personal_id', 'service_id',
        'starts_at', 'ends_at', 'status', 'price', 'notes', 'created_by',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'price' => 'decimal:2',
        'deleted_at' => 'datetime',
    ];

    /** Estados disponibles: slug => [etiqueta, color hex, clase badge] */
    public const STATUSES = [
        'pendiente'  => ['Pendiente',  '#f59e0b', 'bg-warning text-dark'],
        'confirmada' => ['Confirmada', '#3b82f6', 'bg-primary'],
        'completada' => ['Completada', '#10b981', 'bg-success'],
        'cancelada'  => ['Cancelada',  '#ef4444', 'bg-danger'],
        'no_show'    => ['No asistió', '#6b7280', 'bg-secondary'],
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function specialist(): BelongsTo
    {
        return $this->belongsTo(Personal::class, 'personal_id');
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status][0] ?? $this->status;
    }

    public function statusColor(): string
    {
        return self::STATUSES[$this->status][1] ?? '#6b7280';
    }

    public function statusBadge(): string
    {
        return self::STATUSES[$this->status][2] ?? 'bg-secondary';
    }
}
