<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Regla de comisión: cuánto se paga, a quién y por qué.
 *
 * Para cada línea de venta se aplica solo la regla activa y vigente de mayor
 * prioridad (ver priority()). Sin regla, no hay comisión.
 */
class CommissionRule extends Model
{
    use BelongsToCompany, HasFactory;

    protected $fillable = [
        'company_id', 'personal_id', 'applies_to', 'service_id', 'service_category_id',
        'type', 'value', 'valid_from', 'valid_until', 'active', 'notes', 'created_by',
    ];

    protected $casts = [
        'value'       => 'decimal:2',
        'valid_from'  => 'date',
        'valid_until' => 'date',
        'active'      => 'boolean',
    ];

    /** Sobre qué se aplica: código => [etiqueta, peso de especificidad]. */
    public const APPLIES_TO = [
        'service'  => ['Un servicio', 3],
        'category' => ['Una categoría de servicios', 2],
        'services' => ['Todos los servicios', 1],
        'products' => ['Todos los productos', 1],
    ];

    public const TYPES = [
        'percent' => 'Porcentaje',
        'fixed'   => 'Monto fijo por unidad',
    ];

    public function specialist(): BelongsTo
    {
        return $this->belongsTo(Personal::class, 'personal_id');
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ServiceCategory::class, 'service_category_id');
    }

    /**
     * Prioridad: gana la más específica. El profesional pesa más que el
     * objetivo, así que "Ana · todos los servicios" gana a "Equipo · Alisado".
     *
     *   6 Profesional + servicio      3 Equipo + servicio
     *   5 Profesional + categoría     2 Equipo + categoría
     *   4 Profesional + todos         1 Equipo + todos
     */
    public function priority(): int
    {
        $target = self::APPLIES_TO[$this->applies_to][1] ?? 0;

        return ($this->personal_id ? 3 : 0) + $target;
    }

    /** ¿Esta regla cubre la línea dada en la fecha dada? */
    public function matches(SaleItem $item, ?int $personalId, ?int $categoryId, Carbon $on): bool
    {
        if (!$this->active || !$this->isValidOn($on)) {
            return false;
        }

        // Los ids pueden llegar como string desde el formulario: se comparan como enteros.
        if ($this->personal_id && (int) $this->personal_id !== (int) $personalId) {
            return false;
        }

        $serviceId = (int) $item->service_id;

        return match ($this->applies_to) {
            'service'  => $serviceId > 0 && $serviceId === (int) $this->service_id,
            'category' => $serviceId > 0 && $categoryId && (int) $categoryId === (int) $this->service_category_id,
            'services' => $serviceId > 0,
            'products' => (int) $item->product_id > 0,
            default    => false,
        };
    }

    public function isValidOn(Carbon $on): bool
    {
        $day = $on->toDateString();

        return (!$this->valid_from || $this->valid_from->toDateString() <= $day)
            && (!$this->valid_until || $this->valid_until->toDateString() >= $day);
    }

    /** Importe de la comisión para una base y una cantidad. */
    public function amountFor(float $base, float $quantity): float
    {
        return $this->type === 'fixed'
            ? round((float) $this->value * $quantity, 2)
            : round($base * (float) $this->value / 100, 2);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }

    // ---- Presentación ----

    public function whoLabel(): string
    {
        return $this->specialist?->full_name ?? 'Todo el equipo';
    }

    public function targetLabel(): string
    {
        return match ($this->applies_to) {
            'service'  => $this->service?->name ?? 'Servicio eliminado',
            'category' => 'Categoría: ' . ($this->category?->name ?? 'eliminada'),
            default    => self::APPLIES_TO[$this->applies_to][0] ?? $this->applies_to,
        };
    }

    public function rateLabel(): string
    {
        return self::formatRate($this->type, (float) $this->value);
    }

    /** "40 %" o "Bs 20 c/u", compartido con la comisión devengada. */
    public static function formatRate(?string $type, float $value): string
    {
        if ($type === 'fixed') {
            return CompanySetting::current()->money($value) . ' c/u';
        }

        return rtrim(rtrim(number_format($value, 2), '0'), '.') . ' %';
    }

    public function validityLabel(): string
    {
        if (!$this->valid_from && !$this->valid_until) {
            return 'Siempre';
        }

        return ($this->valid_from?->format('d/m/Y') ?? '…') . ' – ' . ($this->valid_until?->format('d/m/Y') ?? '…');
    }
}
