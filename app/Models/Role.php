<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Role extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['company_id', 'name', 'slug', 'description'];

    protected $casts = [
        'deleted_at' => 'datetime',
    ];

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'role_permission');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'company_user');
    }

    public function cargos(): HasMany
    {
        return $this->hasMany(Cargo::class);
    }

    /** Roles que puede usar una empresa: los generales (sin empresa) y los suyos. */
    public function scopeAvailableTo(Builder $query, ?int $companyId): Builder
    {
        return $query->where(fn ($q) => $q->whereNull('company_id')->orWhere('company_id', $companyId));
    }

    public function isAvailableTo(?int $companyId): bool
    {
        return $this->company_id === null || (int) $this->company_id === (int) $companyId;
    }

    /** Slug libre (también entre los eliminados: la columna es única). */
    public static function uniqueSlug(string $name): string
    {
        $base = Str::slug($name, '_') ?: 'role';
        $slug = $base;
        $counter = 1;
        while (static::withTrashed()->where('slug', $slug)->exists()) {
            $slug = $base . '_' . $counter++;
        }

        return $slug;
    }
}
