<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use App\Support\Tenancy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class PaymentMethod extends Model
{
    use BelongsToCompany, HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id', 'name', 'code', 'icon', 'affects_cash',
        'is_default', 'active', 'sort_order', 'created_by',
    ];

    protected $casts = [
        'affects_cash' => 'boolean',
        'is_default'   => 'boolean',
        'active'       => 'boolean',
        'deleted_at'   => 'datetime',
    ];

    /**
     * Catálogo mínimo usado cuando la empresa todavía no configuró sus métodos.
     * Mantiene el comportamiento histórico del POS y de la caja.
     */
    public const FALLBACK = [
        'efectivo'      => ['Efectivo', 'bi-cash-coin', true],
        'tarjeta'       => ['Tarjeta', 'bi-credit-card', false],
        'transferencia' => ['Transferencia', 'bi-bank', false],
        'qr'            => ['QR', 'bi-qr-code', false],
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /** Iconos sugeridos para el formulario. */
    public static function iconOptions(): array
    {
        return [
            'bi-cash-coin'              => 'Efectivo',
            'bi-credit-card'            => 'Tarjeta',
            'bi-bank'                   => 'Banco / Transferencia',
            'bi-qr-code'                => 'QR',
            'bi-wallet2'                => 'Billetera digital',
            'bi-phone'                  => 'Pago por celular',
            'bi-ticket-perforated'      => 'Vale / Cupón',
            'bi-credit-card-2-front'    => 'Genérico',
        ];
    }

    /**
     * Métodos activos de la empresa como [code => name].
     * Si la empresa no tiene ninguno configurado devuelve el catálogo base.
     */
    public static function optionsFor(?int $companyId = null): array
    {
        $companyId = $companyId ?? app(Tenancy::class)->id();

        if (!$companyId) {
            return self::fallbackOptions();
        }

        $options = static::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->pluck('name', 'code')
            ->all();

        return $options ?: self::fallbackOptions();
    }

    public static function fallbackOptions(): array
    {
        return array_map(fn ($m) => $m[0], self::FALLBACK);
    }

    /** Código del método marcado como predeterminado (o el primero disponible). */
    public static function defaultCodeFor(?int $companyId = null): string
    {
        $companyId = $companyId ?? app(Tenancy::class)->id();

        if ($companyId) {
            $default = static::withoutGlobalScopes()
                ->where('company_id', $companyId)
                ->where('active', true)
                ->where('is_default', true)
                ->value('code');

            if ($default) {
                return $default;
            }
        }

        return (string) array_key_first(self::optionsFor($companyId));
    }

    /** Etiqueta legible de un código, aunque el método ya no exista. */
    public static function label(?string $code, ?int $companyId = null): string
    {
        if (!$code) {
            return '-';
        }

        return self::optionsFor($companyId)[$code]
            ?? self::fallbackOptions()[$code]
            ?? Str::title(str_replace(['_', '-'], ' ', $code));
    }

    /** Siembra el catálogo base para una empresa que aún no tiene métodos. */
    public static function seedDefaultsFor(int $companyId): void
    {
        if (static::withoutGlobalScopes()->where('company_id', $companyId)->exists()) {
            return;
        }

        $order = 0;
        foreach (self::FALLBACK as $code => [$name, $icon, $affectsCash]) {
            static::withoutGlobalScopes()->create([
                'company_id'   => $companyId,
                'name'         => $name,
                'code'         => $code,
                'icon'         => $icon,
                'affects_cash' => $affectsCash,
                'is_default'   => $code === 'efectivo',
                'active'       => true,
                'sort_order'   => $order += 10,
            ]);
        }
    }
}
