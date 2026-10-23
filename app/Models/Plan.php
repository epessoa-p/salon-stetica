<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Plan extends Model
{
    use HasFactory, SoftDeletes;

    /** Catálogo de módulos que un plan puede incluir (features). */
    public const MODULES = [
        'crm'        => 'Clientes',
        'scheduling' => 'Agenda y Citas',
        'catalog'    => 'Servicios',
        'sales'      => 'Ventas / POS',
        'inventory'  => 'Inventario',
        'purchasing' => 'Compras',
        'finance'    => 'Caja y Finanzas',
        'commissions' => 'Comisiones',
        'loyalty'    => 'Membresías y fidelización',
        'marketing'  => 'Marketing',
    ];

    /**
     * Mapa permission.module → feature de plan que lo habilita.
     * Los módulos que NO están aquí son administrativos/datos maestros
     * y están SIEMPRE disponibles (no dependen del plan).
     */
    public const PERMISSION_MODULE_FEATURES = [
        'clients' => 'crm',
        'appointments' => 'scheduling',
        'services' => 'catalog', 'service-categories' => 'catalog',
        'sales' => 'sales', 'quotes' => 'sales', 'returns' => 'sales',
        'combos' => 'catalog', 'packages' => 'catalog',
        'products' => 'inventory', 'product-categories' => 'inventory', 'brands' => 'inventory', 'stock' => 'inventory',
        'suppliers' => 'purchasing', 'purchases' => 'purchasing', 'purchase-returns' => 'purchasing',
        'cash' => 'finance', 'cajas' => 'finance', 'incomes' => 'finance', 'expenses' => 'finance',
        'commissions' => 'commissions', 'commission-rules' => 'commissions',
        'memberships' => 'loyalty', 'loyalty' => 'loyalty',
        'marketing' => 'marketing',
        // 'company','users','roles','branches','cargos','personal','reports',
        // 'plans','subscriptions','payments' → administrativos = siempre disponibles.
    ];

    public static function featureForPermissionModule(string $module): ?string
    {
        return self::PERMISSION_MODULE_FEATURES[$module] ?? null;
    }

    protected $fillable = [
        'name', 'slug', 'description', 'price', 'currency', 'billing_days', 'trial_days',
        'max_branches', 'max_personal', 'max_cajas', 'max_users', 'features', 'active', 'sort_order',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'features' => 'array',
        'active' => 'boolean',
        'deleted_at' => 'datetime',
    ];

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    /**
     * Límite de un recurso ('branches', 'personal', 'cajas', 'users'). Null = ilimitado.
     */
    public function limitFor(string $resource): ?int
    {
        return match ($resource) {
            'branches' => $this->max_branches,
            'personal' => $this->max_personal,
            'cajas'    => $this->max_cajas,
            'users'    => $this->max_users,
            default    => null,
        };
    }

    /** ¿El plan incluye este módulo? (features null = sin restricción configurada) */
    public function allows(string $feature): bool
    {
        if ($this->features === null) {
            return true;
        }
        return in_array($feature, $this->features, true);
    }
}
