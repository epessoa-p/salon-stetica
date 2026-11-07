<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * En qué se gasta. Cada empresa define las suyas; la primera vez que entra a
 * Finanzas recibe las habituales. El tipo que ve el usuario (general, servicio
 * básico, a proveedor, a personal) se guarda como payee + recurring.
 */
class ExpenseCategory extends Model
{
    use BelongsToCompany, SoftDeletes;

    /** La categoría de las liquidaciones de comisiones: la usa el sistema. */
    public const COMMISSIONS = 'COMISIONES';

    public const TYPES = [
        'general'   => 'Gasto general',
        'basicos'   => 'Servicio básico',
        'proveedor' => 'Pago a proveedor',
        'personal'  => 'Pago a personal',
    ];

    public const GROUP_TITLES = [
        'general'   => 'Gastos generales',
        'basicos'   => 'Servicios básicos',
        'proveedor' => 'Pagos a proveedores',
        'personal'  => 'Pagos a personal',
    ];

    public const FREQUENCIES = ['mensual' => 'Mensual', 'anual' => 'Anual'];

    /** Nombre, payee, recurring. */
    public const DEFAULTS = [
        ['PAGO A PROVEEDORES', 'proveedor', false],
        ['PAGO A PERSONAL', 'personal', false],
        ['ALQUILER', 'ninguno', true],
        ['LUZ', 'ninguno', true],
        ['AGUA', 'ninguno', true],
        ['INTERNET Y TELÉFONO', 'ninguno', true],
        ['COMIDA', 'ninguno', false],
        ['PASAJES Y TRANSPORTE', 'ninguno', false],
        ['PEDIDOS Y ENVÍOS', 'ninguno', false],
        ['LIMPIEZA Y MANTENIMIENTO', 'ninguno', false],
        ['OTROS', 'ninguno', false],
    ];

    protected $fillable = ['company_id', 'name', 'payee', 'recurring', 'frequency', 'active', 'sort_order'];

    protected $casts = [
        'recurring'  => 'boolean',
        'active'     => 'boolean',
        'sort_order' => 'integer',
        'deleted_at' => 'datetime',
    ];

    public function expenses(): HasMany
    {
        return $this->hasMany(CashMovement::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    /** Los grupos no se solapan: servicio básico manda; luego, a quién se le paga. */
    public function scopeInGroup(Builder $query, ?string $group): Builder
    {
        return match ($group) {
            'basicos'               => $query->where('recurring', true),
            'proveedor', 'personal' => $query->where('recurring', false)->where('payee', $group),
            'general'               => $query->where('recurring', false)->where('payee', 'ninguno'),
            default                 => $query,
        };
    }

    public function group(): string
    {
        return $this->recurring ? 'basicos' : (in_array($this->payee, ['proveedor', 'personal'], true) ? $this->payee : 'general');
    }

    public function isAnnual(): bool
    {
        return $this->recurring && $this->frequency === 'anual';
    }

    /** Validación del alta y la edición, la misma en la web y la API. */
    public static function validateForm(Request $request, int $companyId, ?self $category = null): array
    {
        $data = $request->validate([
            'name'      => ['required', 'string', 'max:120', Rule::unique('expense_categories', 'name')->where('company_id', $companyId)->whereNull('deleted_at')->ignore($category?->id)],
            'type'      => ['required', Rule::in(array_keys(self::TYPES))],
            'frequency' => ['nullable', Rule::in(array_keys(self::FREQUENCIES))],
        ], [
            'name.required' => 'Ponle un nombre a la categoría.',
            'name.unique'   => 'Ya hay una categoría con ese nombre.',
            'type.required' => 'Elige el tipo de gasto.',
        ]);

        return ['name' => mb_strtoupper(trim($data['name']))] + match ($data['type']) {
            'basicos'               => ['payee' => 'ninguno', 'recurring' => true, 'frequency' => $data['frequency'] ?? 'mensual'],
            'proveedor', 'personal' => ['payee' => $data['type'], 'recurring' => false, 'frequency' => null],
            default                 => ['payee' => 'ninguno', 'recurring' => false, 'frequency' => null],
        };
    }

    /** Crea las habituales si la empresa todavía no tiene ninguna. */
    public static function ensureDefaults(int $companyId): void
    {
        if (static::withoutGlobalScopes()->withTrashed()->where('company_id', $companyId)->exists()) {
            return;
        }

        foreach (self::DEFAULTS as $i => [$name, $payee, $recurring]) {
            static::withoutGlobalScopes()->create([
                'company_id' => $companyId,
                'name'       => $name,
                'payee'      => $payee,
                'recurring'  => $recurring,
                'frequency'  => $recurring ? 'mensual' : null,
                'active'     => true,
                'sort_order' => $i,
            ]);
        }
    }

    /**
     * La categoría de un pago a proveedor o a personal cuando no se elige: la
     * habitual; si la renombraron, la primera del grupo que no sea COMISIONES;
     * si no hay ninguna, se crea.
     */
    public static function defaultFor(int $companyId, string $group): self
    {
        self::ensureDefaults($companyId);
        $name = $group === 'personal' ? 'PAGO A PERSONAL' : 'PAGO A PROVEEDORES';
        $inGroup = static::withoutGlobalScopes()->where('company_id', $companyId)->active()->inGroup($group);

        return (clone $inGroup)->where('name', $name)->first()
            ?? (clone $inGroup)->where('name', '!=', self::COMMISSIONS)->ordered()->first()
            ?? self::system($companyId, $name, $group);
    }

    /** Categoría que usa el sistema: se crea si no existe y se reactiva si estaba de baja. */
    public static function system(int $companyId, string $name, string $payee = 'ninguno'): self
    {
        self::ensureDefaults($companyId);
        $category = static::withoutGlobalScopes()->withTrashed()->where('company_id', $companyId)->where('name', $name)->first();

        if (!$category) {
            return static::withoutGlobalScopes()->create([
                'company_id' => $companyId,
                'name'       => $name,
                'payee'      => $payee,
                'active'     => true,
                'sort_order' => (int) static::withoutGlobalScopes()->where('company_id', $companyId)->max('sort_order') + 1,
            ]);
        }

        if ($category->trashed()) {
            $category->restore();
        }
        if (!$category->active) {
            $category->update(['active' => true]);
        }

        return $category;
    }
}
