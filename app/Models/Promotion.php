<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Promoción automática: se aplica sola en el punto de venta a las líneas que
 * cubre, en los días y fechas configurados (ej. «Martes 20 % en manicura»).
 */
class Promotion extends Model
{
    use BelongsToCompany, HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id', 'name', 'discount_type', 'value', 'applies_to', 'service_id', 'service_category_id',
        'weekdays', 'starts_on', 'ends_on', 'active', 'created_by',
    ];

    protected $casts = [
        'value'      => 'decimal:2',
        'starts_on'  => 'date',
        'ends_on'    => 'date',
        'active'     => 'boolean',
        'deleted_at' => 'datetime',
    ];

    public const APPLIES = [
        'services' => 'Todos los servicios',
        'products' => 'Todos los productos',
        'all'      => 'Servicios y productos',
        'service'  => 'Un servicio',
        'category' => 'Una categoría de servicios',
    ];

    public const WEEKDAYS = [1 => 'Lun', 2 => 'Mar', 3 => 'Mié', 4 => 'Jue', 5 => 'Vie', 6 => 'Sáb', 7 => 'Dom'];

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class)->withoutGlobalScopes();
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ServiceCategory::class, 'service_category_id')->withoutGlobalScopes();
    }

    /** @return int[] días ISO (1 = lunes); vacío = todos */
    public function weekdayList(): array
    {
        return $this->weekdays ? array_map('intval', explode(',', $this->weekdays)) : [];
    }

    public function isValidOn(Carbon $day): bool
    {
        $d = $day->toDateString();

        return $this->active
            && (!$this->starts_on || $this->starts_on->toDateString() <= $d)
            && (!$this->ends_on || $this->ends_on->toDateString() >= $d)
            && (!$this->weekdayList() || in_array($day->dayOfWeekIso, $this->weekdayList(), true));
    }

    /** ¿Cubre esta línea? $kind: service | product. */
    public function covers(string $kind, ?int $serviceId, ?int $categoryId): bool
    {
        return match ($this->applies_to) {
            'services' => $kind === 'service',
            'products' => $kind === 'product',
            'all'      => in_array($kind, ['service', 'product'], true),
            'service'  => $kind === 'service' && (int) $serviceId === (int) $this->service_id,
            'category' => $kind === 'service' && $categoryId && (int) $categoryId === (int) $this->service_category_id,
            default    => false,
        };
    }

    /** Descuento para una línea: % sobre el monto, o monto fijo por unidad (sin superar la línea). */
    public function discountFor(float $amount, float $qty): float
    {
        $d = $this->discount_type === 'percent'
            ? $amount * (float) $this->value / 100
            : (float) $this->value * $qty;

        return round(max(0, min($amount, $d)), 2);
    }

    public function valueLabel(?string $currency = null): string
    {
        return $this->discount_type === 'percent'
            ? rtrim(rtrim(number_format((float) $this->value, 2), '0'), '.') . ' %'
            : ($currency ?? 'Bs') . ' ' . number_format((float) $this->value, 2) . ' c/u';
    }

    public function scheduleLabel(): string
    {
        $days = $this->weekdayList();
        $parts = [$days ? implode(', ', array_map(fn ($d) => self::WEEKDAYS[$d], $days)) : 'Todos los días'];
        if ($this->starts_on || $this->ends_on) {
            $parts[] = trim(($this->starts_on ? 'desde ' . $this->starts_on->format('d/m/Y') : '') . ($this->ends_on ? ' hasta ' . $this->ends_on->format('d/m/Y') : ''));
        }

        return implode(' · ', $parts);
    }

    public function targetLabel(): string
    {
        return match ($this->applies_to) {
            'service'  => $this->service?->name ?? 'Servicio eliminado',
            'category' => 'Categoría ' . ($this->category?->name ?? 'eliminada'),
            default    => self::APPLIES[$this->applies_to] ?? $this->applies_to,
        };
    }
}
