<?php

namespace App\Models;

use App\Support\Tenancy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Preferencias por empresa: moneda, impuestos, datos de facturación y folios.
 * Siempre se obtiene vía CompanySetting::current(); si la empresa no guardó
 * nada todavía devuelve una instancia con los valores por defecto.
 */
class CompanySetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'currency', 'currency_symbol',
        'tax_rate', 'tax_included', 'tax_label',
        'invoice_business_name', 'invoice_nit', 'invoice_address', 'invoice_phone', 'invoice_footer',
        'sale_prefix', 'sale_padding',
        'appointment_slot_minutes', 'require_cash_session',
    ];

    protected $casts = [
        'tax_rate'                 => 'decimal:2',
        'tax_included'             => 'boolean',
        'require_cash_session'     => 'boolean',
        'sale_padding'             => 'integer',
        'appointment_slot_minutes' => 'integer',
    ];

    protected $attributes = [
        'currency'                 => 'BOB',
        'currency_symbol'          => 'Bs',
        'tax_rate'                 => 0,
        'tax_included'             => true,
        'tax_label'                => 'IVA',
        'sale_prefix'              => 'V-',
        'sale_padding'             => 6,
        'appointment_slot_minutes' => 30,
        'require_cash_session'     => false,
    ];

    /** Monedas sugeridas: código => [nombre, símbolo]. */
    public const CURRENCIES = [
        'BOB' => ['Boliviano', 'Bs'],
        'USD' => ['Dólar estadounidense', '$'],
        'ARS' => ['Peso argentino', '$'],
        'CLP' => ['Peso chileno', '$'],
        'PEN' => ['Sol peruano', 'S/'],
        'COP' => ['Peso colombiano', '$'],
        'MXN' => ['Peso mexicano', '$'],
        'EUR' => ['Euro', '€'],
    ];

    /** Caché por request para no consultar en cada vista. */
    protected static array $cache = [];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public static function current(?int $companyId = null): self
    {
        $companyId = $companyId ?? app(Tenancy::class)->id();
        $key = $companyId ?? 0;

        if (!array_key_exists($key, static::$cache)) {
            static::$cache[$key] = $companyId
                ? static::firstOrNew(['company_id' => $companyId])
                : new static();
        }

        return static::$cache[$key];
    }

    public static function forget(?int $companyId = null): void
    {
        if ($companyId === null) {
            static::$cache = [];
            return;
        }

        unset(static::$cache[$companyId]);
    }

    /** Formatea un monto con el símbolo configurado. */
    public function money($amount): string
    {
        return $this->currency_symbol . ' ' . number_format((float) $amount, 2);
    }

    /** Folio siguiente a partir de un consecutivo. */
    public function folio(int $consecutive): string
    {
        return $this->sale_prefix . str_pad((string) $consecutive, max(1, $this->sale_padding), '0', STR_PAD_LEFT);
    }

    /** Impuesto sobre un monto gravado. Devuelve 0 si no hay tasa configurada. */
    public function taxOn(float $amount): float
    {
        $rate = (float) $this->tax_rate;

        if ($rate <= 0) {
            return 0.0;
        }

        return $this->tax_included
            ? round($amount - ($amount / (1 + $rate / 100)), 2)
            : round($amount * $rate / 100, 2);
    }
}
