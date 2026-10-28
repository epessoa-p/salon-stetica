<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Foto de antes/después de un cliente. El archivo es privado (disco local). */
class ClientPhoto extends Model
{
    use BelongsToCompany;

    protected $fillable = ['company_id', 'client_id', 'appointment_id', 'service_id', 'kind', 'path', 'thumb_path', 'taken_on', 'notes', 'uploaded_by'];

    protected $casts = ['taken_on' => 'date'];

    public const KINDS = [
        'before' => 'Antes',
        'after'  => 'Después',
        'other'  => 'Otra',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class)->withoutGlobalScopes();
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function kindLabel(): string
    {
        return self::KINDS[$this->kind] ?? $this->kind;
    }
}
