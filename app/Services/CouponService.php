<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Coupon;
use App\Models\Promotion;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Cupones y promociones del punto de venta.
 *
 * Reglas de Glamora:
 *  - El cupón se escribe en el punto de venta y descuenta sobre las líneas que
 *    cubre (toda la venta, solo servicios o solo productos), después de los
 *    descuentos de línea y del descuento global. Un cupón por venta.
 *  - Las promociones se aplican solas a cada línea de servicio o producto
 *    según el día. No se suman a la membresía: el cliente recibe la mayor.
 *    Las sesiones prepagadas y los combos no reciben promociones.
 *  - Ni el cupón ni la promoción bajan la comisión del profesional (como la
 *    membresía: es un descuento que asume el salón).
 */
class CouponService
{
    public function find(int $companyId, ?string $code): ?Coupon
    {
        $code = mb_strtoupper(trim((string) $code));
        if ($code === '') {
            return null;
        }

        return Coupon::withoutGlobalScopes()->whereNull('deleted_at')->where('company_id', $companyId)->where('code', $code)->first();
    }

    /**
     * Valida el cupón para esta venta y calcula su descuento.
     *
     * @param  float  $payable  total a pagar antes del cupón (para la compra mínima y el tope)
     * @param  float  $eligible monto de las líneas que el cupón cubre
     * @return array{0: float, 1: ?string} [descuento, error]
     */
    public function evaluate(Coupon $coupon, ?Client $client, float $payable, float $eligible, ?Carbon $on = null): array
    {
        $day = ($on ?? today())->toDateString();
        $code = $coupon->code;

        $error = match (true) {
            !$coupon->active                                                          => "El cupón {$code} no está activo.",
            $coupon->starts_on && $coupon->starts_on->toDateString() > $day           => "El cupón {$code} rige desde el " . $coupon->starts_on->format('d/m/Y') . '.',
            $coupon->ends_on && $coupon->ends_on->toDateString() < $day               => "El cupón {$code} venció el " . $coupon->ends_on->format('d/m/Y') . '.',
            $coupon->client_id && !$client                                            => "El cupón {$code} es personal: elige al cliente.",
            $coupon->client_id && (int) $coupon->client_id !== (int) $client?->id     => "El cupón {$code} es de otro cliente.",
            $coupon->max_uses !== null && $coupon->uses() >= $coupon->max_uses        => "El cupón {$code} ya se usó todas las veces permitidas.",
            $coupon->max_uses_per_client !== null && !$client                         => "Para usar el cupón {$code} hay que elegir al cliente.",
            $coupon->max_uses_per_client !== null && $coupon->uses($client->id) >= $coupon->max_uses_per_client
                                                                                      => "El cliente ya usó el cupón {$code}.",
            $coupon->min_purchase !== null && $payable < (float) $coupon->min_purchase => "El cupón {$code} pide una compra mínima de " . number_format((float) $coupon->min_purchase, 2) . '.',
            $eligible <= 0                                                            => "El cupón {$code} no aplica a lo que hay en esta venta.",
            default                                                                   => null,
        };

        if ($error) {
            return [0.0, $error];
        }

        $discount = $coupon->discount_type === 'percent'
            ? $eligible * (float) $coupon->value / 100
            : (float) $coupon->value;
        if ($coupon->discount_type === 'percent' && $coupon->max_discount !== null) {
            $discount = min($discount, (float) $coupon->max_discount);
        }

        return [round(max(0, min($discount, $eligible, $payable)), 2), null];
    }

    /** Promociones vigentes en un día. */
    public function promotionsOn(int $companyId, ?Carbon $day = null): Collection
    {
        $day ??= today();

        return Promotion::withoutGlobalScopes()->whereNull('deleted_at')->where('company_id', $companyId)->where('active', true)->get()
            ->filter(fn (Promotion $p) => $p->isValidOn($day))
            ->values();
    }

    /**
     * La promoción que más descuenta en una línea.
     *
     * @return array{0: ?Promotion, 1: float}
     */
    public function bestPromotion(Collection $promotions, string $kind, ?int $serviceId, ?int $categoryId, float $amount, float $qty): array
    {
        $best = null;
        $bestDiscount = 0.0;

        foreach ($promotions as $promotion) {
            if (!$promotion->covers($kind, $serviceId, $categoryId)) {
                continue;
            }
            $d = $promotion->discountFor($amount, $qty);
            if ($d > $bestDiscount) {
                [$best, $bestDiscount] = [$promotion, $d];
            }
        }

        return [$best, $bestDiscount];
    }
}
