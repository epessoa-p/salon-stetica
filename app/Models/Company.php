<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Company extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name', 'ruc', 'address', 'phone', 'email', 'logo', 'description', 'active',
        'primary_color', 'secondary_color',
    ];

    protected $casts = [
        'active' => 'boolean',
        'deleted_at' => 'datetime',
    ];

    /**
     * Usuarios que pertenecen a esta empresa
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'company_user')
                    ->withPivot('role_id', 'active');
    }

    /**
     * Préstamos de esta empresa
     */
    public function loans(): HasMany
    {
        return $this->hasMany(Loan::class);
    }

    /**
     * Tipos de préstamos de esta empresa
     */
    public function loanTypes(): HasMany
    {
        return $this->hasMany(LoanType::class);
    }

    public function clients(): HasMany
    {
        return $this->hasMany(Client::class);
    }

    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function creditCategories(): HasMany
    {
        return $this->hasMany(CreditCategory::class);
    }

    public function creditCategoryRules(): HasMany
    {
        return $this->hasMany(CreditCategoryRule::class);
    }

    public function warehouses(): HasMany
    {
        return $this->hasMany(Warehouse::class);
    }

    public function inventoryMovements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class);
    }

    public function cargos(): HasMany
    {
        return $this->hasMany(Cargo::class);
    }

    public function personals(): HasMany
    {
        return $this->hasMany(Personal::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function serviceCategories(): HasMany
    {
        return $this->hasMany(ServiceCategory::class);
    }

    public function services(): HasMany
    {
        return $this->hasMany(Service::class);
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    public function suppliers(): HasMany
    {
        return $this->hasMany(Supplier::class);
    }

    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class);
    }

    public function productCategories(): HasMany
    {
        return $this->hasMany(ProductCategory::class);
    }

    public function brands(): HasMany
    {
        return $this->hasMany(Brand::class);
    }

    /**
     * Suscripción vigente (la más reciente).
     */
    public function currentSubscription(): ?Subscription
    {
        return $this->subscriptions()->with('plan')->latest('id')->first();
    }

    /**
     * Estado de la suscripción: trial | active | grace | expired | none.
     */
    public function subscriptionStatus(): string
    {
        $sub = $this->currentSubscription();
        return $sub ? $sub->computedStatus() : 'none';
    }

    /**
     * ¿La empresa tiene acceso al sistema? (prueba/activa/gracia).
     */
    public function hasAccess(): bool
    {
        return in_array($this->subscriptionStatus(), ['trial', 'active', 'grace'], true);
    }

    /**
     * Límite del plan para un recurso ('branches','personal','cajas','users'). Null = ilimitado.
     */
    public function planLimit(string $resource): ?int
    {
        return $this->currentSubscription()?->plan?->limitFor($resource);
    }

    /**
     * Conteo actual de un recurso dentro de la empresa.
     */
    public function resourceCount(string $resource): int
    {
        return match ($resource) {
            'branches' => $this->branches()->count(),
            'personal' => $this->personals()->count(),
            'cajas'    => \App\Models\Caja::where('company_id', $this->id)->count(),
            'users'    => $this->users()->count(),
            default    => 0,
        };
    }

    /**
     * ¿Puede crear un recurso más según el límite del plan?
     */
    public function canCreate(string $resource): bool
    {
        $limit = $this->planLimit($resource);
        if ($limit === null) {
            return true; // ilimitado o sin plan con límite
        }
        return $this->resourceCount($resource) < $limit;
    }

    /**
     * Obtener el rol de un usuario dentro de esta empresa
     */
    public function getRoleForUser(User $user): ?Role
    {
        $pivot = $this->users()
                      ->where('user_id', $user->id)
                      ->first()?->pivot;

        return $pivot ? Role::find($pivot->role_id) : null;
    }

    /**
     * Obtener permisos efectivos (del rol + permisos adicionales)
     */
    public function getPermissionsForUser(User $user): array
    {
        if ($user->is_super_admin) {
            return Permission::all()->pluck('slug')->toArray();
        }

        $role = $this->getRoleForUser($user);
        $permissions = [];

        if ($role) {
            $permissions = $role->permissions()->pluck('slug')->toArray();
        }

        // Agregar permisos adicionales directos
        $directPermissions = \DB::table('user_permission')
            ->where('user_id', $user->id)
            ->where('company_id', $this->id)
            ->join('permissions', 'user_permission.permission_id', '=', 'permissions.id')
            ->pluck('permissions.slug')
            ->toArray();

        return array_unique(array_merge($permissions, $directPermissions));
    }
}
