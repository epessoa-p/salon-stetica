<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Company;
use App\Models\CompanySetting;
use App\Models\MembershipPlan;
use App\Models\PaymentMethod;
use App\Models\Personal;
use App\Models\Product;
use App\Models\Service;
use Illuminate\Validation\Rule;

/**
 * Cálculo de una venta del POS: una sola fuente de verdad.
 *
 * `quote()` lo usa el POS en vivo (para mostrar totales, beneficios y
 * errores) y `SaleController::store()` al cobrar, así lo que ve la cajera y
 * lo que se registra nunca difieren.
 *
 * Orden del cálculo por línea:
 *   bruto = cantidad × precio
 *   − descuento manual de la línea
 *   − beneficio de membresía: sesión incluida (la línea queda en 0) o el %
 * y sobre el total de la venta:
 *   − descuento global − canje de puntos → gravado → impuesto → total
 */
class CheckoutService
{
    public function __construct(
        protected MembershipService $memberships,
        protected LoyaltyService $loyalty,
    ) {
    }

    /** Reglas de validación del carrito (POS en vivo y cobro). */
    public static function rules(int $companyId, bool $forStore = true): array
    {
        $required = $forStore ? 'required' : 'nullable';

        return [
            'client_id'       => ['nullable', 'integer'],
            'client_name'     => ['nullable', 'string', 'max:255'],
            'personal_id'     => ['nullable', 'integer'],
            'branch_id'       => ['nullable', 'exists:branches,id'],
            'cash_session_id' => ['nullable', 'exists:cash_sessions,id'],
            'payment_method'  => [$required, 'string', 'max:50', Rule::in(array_keys(PaymentMethod::optionsFor($companyId)))],
            'global_discount' => ['nullable', 'numeric', 'min:0'],
            'redeem_points'   => ['nullable', 'integer', 'min:0'],
            'tip'             => ['nullable', 'numeric', 'min:0'],
            'notes'           => ['nullable', 'string'],
            'items'           => [$required, 'array', $forStore ? 'min:1' : 'min:0'],
            'items.*.service_id'         => ['nullable', 'integer'],
            'items.*.product_id'         => ['nullable', 'integer'],
            'items.*.membership_plan_id' => ['nullable', 'integer'],
            'items.*.description'        => ['nullable', 'string', 'max:255'],
            'items.*.quantity'           => ['required', 'numeric', 'min:0.01'],
            'items.*.unit_price'         => ['required', 'numeric', 'min:0'],
            'items.*.discount'           => ['nullable', 'numeric', 'min:0'],
            'items.*.personal_id'        => ['nullable', 'integer'],
        ];
    }

    /**
     * Desglose autoritativo del carrito. Devuelve líneas, totales, datos del
     * cliente y errores (que impiden cobrar) y avisos (informativos).
     */
    public function quote(array $data, Company $company): array
    {
        $companyId = $company->id;
        $setting = CompanySetting::current($companyId);
        $errors = $this->ownershipErrors($data, $company);
        $warnings = [];

        $loyaltyOn = $this->loyalty->enabled($company);
        $membershipsOn = $company->planAllows('loyalty');

        $client = !empty($data['client_id'])
            ? Client::withoutGlobalScopes()->where('company_id', $companyId)->find($data['client_id'])
            : null;
        $membership = ($client && $membershipsOn) ? $this->memberships->activeFor($companyId, $client->id) : null;
        $remaining = $membership ? $this->memberships->remainingSessions($membership) : [];

        $items = $data['items'] ?? [];
        $services = Service::withoutGlobalScopes()->where('company_id', $companyId)
            ->whereIn('id', collect($items)->pluck('service_id')->filter())->get()->keyBy('id');
        $plans = MembershipPlan::withoutGlobalScopes()->where('company_id', $companyId)
            ->whereIn('id', collect($items)->pluck('membership_plan_id')->filter())->get()->keyBy('id');

        $lines = [];
        $subtotal = $lineDiscounts = $membershipDiscount = 0.0;

        foreach ($items as $i => $item) {
            $kind = !empty($item['membership_plan_id']) ? 'membership'
                : (!empty($item['product_id']) ? 'product'
                : (!empty($item['service_id']) ? 'service' : 'custom'));

            $qty = (float) $item['quantity'];
            if ($kind === 'membership') {
                $qty = 1; // una membresía por línea: se activa una sola vez
                $plan = $plans[$item['membership_plan_id']] ?? null;
                if (!$membershipsOn) {
                    $errors[] = 'El plan de la empresa no incluye membresías.';
                } elseif (!$plan || !$plan->active) {
                    $errors[] = 'El plan de membresía elegido no está disponible.';
                } elseif (!$client) {
                    $errors[] = 'Para vender una membresía hay que elegir el cliente que la recibe.';
                }
            }

            $price = (float) $item['unit_price'];
            $gross = round($qty * $price, 2);
            $manual = min($gross, round((float) ($item['discount'] ?? 0), 2));
            $memberDiscount = 0.0;
            $usesSession = false;
            $badge = null;

            if ($membership && $kind === 'service') {
                $serviceId = (int) $item['service_id'];
                $left = $remaining[$serviceId] ?? 0;

                if ($left > 0 && $left >= $qty) {
                    // Sesión incluida: la línea queda en 0 y descuenta del saldo.
                    $usesSession = true;
                    $memberDiscount = round($gross - $manual, 2);
                    $remaining[$serviceId] = $left - (int) $qty;
                    $badge = 'Sesión incluida (quedan ' . $remaining[$serviceId] . ')';
                } elseif ($membership->covers('service', $services[$serviceId]->service_category_id ?? null)) {
                    $memberDiscount = $this->percent($gross - $manual, (float) $membership->discount_percent);
                }
            } elseif ($membership && $kind === 'product' && $membership->covers('product')) {
                $memberDiscount = $this->percent($gross - $manual, (float) $membership->discount_percent);
            }

            if (!$usesSession && $memberDiscount > 0) {
                $badge = '−' . rtrim(rtrim(number_format((float) $membership->discount_percent, 2), '0'), '.') . ' % membresía';
            }

            $total = max(0, round($gross - $manual - $memberDiscount, 2));
            $subtotal += $gross;
            $lineDiscounts += $manual;
            $membershipDiscount += $memberDiscount;

            $lines[] = [
                'index'                => $i,
                'kind'                 => $kind,
                'service_id'           => $kind === 'service' ? (int) $item['service_id'] : null,
                'product_id'           => $kind === 'product' ? (int) $item['product_id'] : null,
                'membership_plan_id'   => $kind === 'membership' ? (int) $item['membership_plan_id'] : null,
                'personal_id'          => !empty($item['personal_id']) ? (int) $item['personal_id'] : null,
                'description'          => $item['description'] ?? null,
                'quantity'             => $qty,
                'unit_price'           => $price,
                'gross'                => $gross,
                'discount'             => $manual,
                'membership_discount'  => $memberDiscount,
                'uses_session'         => $usesSession,
                'client_membership_id' => ($usesSession || $memberDiscount > 0) ? $membership->id : null,
                'total'                => $total,
                'badge'                => $badge,
            ];
        }

        $afterLines = max(0, round($subtotal - $lineDiscounts - $membershipDiscount, 2));
        $globalDiscount = min($afterLines, round((float) ($data['global_discount'] ?? 0), 2));
        $payable = round($afterLines - $globalDiscount, 2);

        // Canje de puntos: actúa como descuento global (lo asume el salón).
        $points = [ 'enabled' => $loyaltyOn && (bool) $client ];
        $redeemPoints = (int) ($data['redeem_points'] ?? 0);
        $loyaltyDiscount = 0.0;

        if ($points['enabled']) {
            $points += $this->loyalty->summary($companyId, $client->id, $payable);

            if ($redeemPoints > 0) {
                if ($redeemPoints > $points['balance']) {
                    $errors[] = "El cliente solo tiene {$points['balance']} puntos.";
                } elseif ($redeemPoints < $points['min_redeem']) {
                    $errors[] = "El canje mínimo es de {$points['min_redeem']} puntos.";
                } elseif ($setting->pointsValue($redeemPoints) > $payable) {
                    $errors[] = "Con este total se pueden canjear hasta {$points['max_redeemable']} puntos.";
                } else {
                    $loyaltyDiscount = $setting->pointsValue($redeemPoints);
                }
            }
        } elseif ($redeemPoints > 0) {
            $errors[] = $client ? 'El programa de puntos no está activo.' : 'Para canjear puntos hay que elegir el cliente.';
        }

        $gravado = max(0, round($payable - $loyaltyDiscount, 2));
        $tax = $setting->taxOn($gravado);
        $total = $setting->tax_included ? $gravado : round($gravado + $tax, 2);
        $tip = round((float) ($data['tip'] ?? 0), 2);

        if ($tip > 0 && empty($data['personal_id'])) {
            $errors[] = 'La propina es del profesional principal: selecciona quién atendió la venta.';
        }

        return [
            'lines'               => $lines,
            'subtotal'            => round($subtotal, 2),
            'line_discounts'      => round($lineDiscounts, 2),
            'global_discount'     => $globalDiscount,
            'membership_discount' => round($membershipDiscount, 2),
            'redeem_points'       => $loyaltyDiscount > 0 ? $redeemPoints : 0,
            'loyalty_discount'    => $loyaltyDiscount,
            'gravado'             => $gravado,
            'tax'                 => $tax,
            'total'               => $total,
            'tip'                 => $tip,
            'charge'              => round($total + $tip, 2),
            'points_to_earn'      => ($loyaltyOn && $client) ? $setting->pointsFor($total) : 0,
            'client'              => $client ? $this->clientSummary($client, $membership, $points) : null,
            'errors'              => array_values(array_unique($errors)),
            'warnings'            => $warnings,
        ];
    }

    /** Ficha corta del cliente para el POS: membresía y puntos. */
    public function clientSummary(Client $client, $membership = false, ?array $points = null): array
    {
        $company = $client->company;

        if ($membership === false) {
            $membership = $company && $company->planAllows('loyalty')
                ? $this->memberships->activeFor($client->company_id, $client->id)
                : null;
        }
        if ($points === null) {
            $points = ['enabled' => $this->loyalty->enabled($company)];
            if ($points['enabled']) {
                $points += $this->loyalty->summary($client->company_id, $client->id);
            }
        }

        return [
            'id'         => $client->id,
            'name'       => $client->full_name,
            'phone'      => $client->phone,
            'document'   => $client->id_number,
            'membership' => $membership ? $this->memberships->summary($membership) : null,
            'points'     => $points,
        ];
    }

    /**
     * Las reglas "exists" no miran la empresa: se verifica que todo lo del
     * carrito sea de la empresa activa y que cada línea tenga qué vender.
     */
    protected function ownershipErrors(array $data, Company $company): array
    {
        $errors = [];
        $items = collect($data['items'] ?? []);

        foreach ($items as $item) {
            if (empty($item['service_id']) && empty($item['product_id']) && empty($item['membership_plan_id']) && empty($item['description'])) {
                $errors[] = 'Cada línea debe tener un servicio, un producto, una membresía o una descripción.';
                break;
            }
        }

        if ($items->pluck('product_id')->filter()->isNotEmpty() && !$company->planAllows('inventory')) {
            $errors[] = 'El plan de la empresa no incluye inventario: no se pueden vender productos.';
        }

        $checks = [
            [Service::class, $items->pluck('service_id')],
            [Product::class, $items->pluck('product_id')],
            [MembershipPlan::class, $items->pluck('membership_plan_id')],
            [Personal::class, $items->pluck('personal_id')->push($data['personal_id'] ?? null)],
            [Client::class, collect([$data['client_id'] ?? null])],
        ];

        foreach ($checks as [$model, $ids]) {
            $ids = $ids->filter()->map(fn ($id) => (int) $id)->unique();
            if ($ids->isNotEmpty()
                && $model::withoutGlobalScopes()->where('company_id', $company->id)->whereIn('id', $ids)->count() !== $ids->count()) {
                $errors[] = 'La venta incluye datos que no pertenecen a la empresa.';
                break;
            }
        }

        return $errors;
    }

    protected function percent(float $base, float $percent): float
    {
        return $percent > 0 ? round(max(0, $base) * $percent / 100, 2) : 0.0;
    }
}
