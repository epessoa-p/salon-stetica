<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Combo;
use App\Models\Company;
use App\Models\CompanySetting;
use App\Models\MembershipPlan;
use App\Models\PaymentMethod;
use App\Models\Personal;
use App\Models\Product;
use App\Models\Service;
use App\Models\ServicePackage;
use Illuminate\Validation\Rule;

/**
 * Cálculo de una venta del POS: una sola fuente de verdad.
 *
 * `quote()` lo usa el POS en vivo (para mostrar totales, beneficios y
 * errores), las cotizaciones y `SaleController::store()` al cobrar, así lo
 * que ve la cajera y lo que se registra nunca difieren.
 *
 * Orden del cálculo por línea:
 *   bruto = cantidad × precio
 *   − descuento manual de la línea
 *   − beneficio prepagado: sesión de membresía o de paquete (la línea queda
 *     en 0) o, si no hay sesión, el % de la membresía
 * y sobre el total de la venta:
 *   − descuento global − canje de puntos → gravado → impuesto → total
 * Lo que se cobra: total + propina − saldo a favor.
 *
 * Un combo es una línea del carrito que al cobrar se desarma en sus
 * componentes; su precio se reparte según el precio de lista de cada uno.
 * Los combos no reciben beneficios de membresía ni paquete: ya tienen precio
 * especial.
 */
class CheckoutService
{
    public function __construct(
        protected MembershipService $memberships,
        protected LoyaltyService $loyalty,
        protected PackageService $packages,
        protected CreditService $credit,
    ) {
    }

    /** Reglas de validación del carrito (POS en vivo, cotización y cobro). */
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
            'credit_amount'   => ['nullable', 'numeric', 'min:0'],
            'tip'             => ['nullable', 'numeric', 'min:0'],
            'notes'           => ['nullable', 'string'],
            'quote_id'        => ['nullable', 'integer'],
            'items'           => [$required, 'array', $forStore ? 'min:1' : 'min:0'],
            'items.*.service_id'         => ['nullable', 'integer'],
            'items.*.product_id'         => ['nullable', 'integer'],
            'items.*.membership_plan_id' => ['nullable', 'integer'],
            'items.*.combo_id'           => ['nullable', 'integer'],
            'items.*.service_package_id' => ['nullable', 'integer'],
            'items.*.description'        => ['nullable', 'string', 'max:255'],
            'items.*.quantity'           => ['required', 'numeric', 'min:0.01'],
            'items.*.unit_price'         => ['required', 'numeric', 'min:0'],
            'items.*.discount'           => ['nullable', 'numeric', 'min:0'],
            'items.*.personal_id'        => ['nullable', 'integer'],
        ];
    }

    /** Tipo de una línea del carrito según qué trae. */
    public static function kindOf(array $item): string
    {
        return match (true) {
            !empty($item['membership_plan_id']) => 'membership',
            !empty($item['service_package_id']) => 'package',
            !empty($item['combo_id'])           => 'combo',
            !empty($item['product_id'])         => 'product',
            !empty($item['service_id'])         => 'service',
            default                             => 'custom',
        };
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

        // Paquetes del cliente: sesiones que quedan por paquete, para irlas
        // descontando a medida que el carrito las usa.
        $clientPackages = $client ? $this->packages->usableFor($companyId, $client->id) : collect();
        $packageLeft = $clientPackages->mapWithKeys(fn ($p) => [$p->id => $this->packages->remaining($p)])->all();

        $items = $data['items'] ?? [];
        $ids = fn (string $field) => collect($items)->pluck($field)->filter()->unique();
        $services = Service::withoutGlobalScopes()->where('company_id', $companyId)->whereIn('id', $ids('service_id'))->get()->keyBy('id');
        $plans = MembershipPlan::withoutGlobalScopes()->where('company_id', $companyId)->whereIn('id', $ids('membership_plan_id'))->get()->keyBy('id');
        // Sin scopes globales (no dependen del tenant de la petición), pero sin los eliminados.
        $servicePackages = ServicePackage::withoutGlobalScopes()->whereNull('deleted_at')->where('company_id', $companyId)->whereIn('id', $ids('service_package_id'))->get()->keyBy('id');
        $combos = Combo::withoutGlobalScopes()->whereNull('deleted_at')->with(['items.service', 'items.product'])
            ->where('company_id', $companyId)->whereIn('id', $ids('combo_id'))->get()->keyBy('id');

        $lines = [];
        $subtotal = $lineDiscounts = $membershipDiscount = $packageDiscount = 0.0;
        $productNeeds = []; // product_id => cantidad que pide el carrito

        foreach ($items as $i => $item) {
            $kind = self::kindOf($item);
            $qty = (float) $item['quantity'];
            $combo = null;

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
            } elseif ($kind === 'package') {
                $qty = 1; // un paquete por línea: se activa una sola vez
                $package = $servicePackages[$item['service_package_id']] ?? null;
                if (!$package || !$package->active) {
                    $errors[] = 'El paquete elegido no está disponible.';
                } elseif (!$client) {
                    $errors[] = 'Para vender un paquete hay que elegir el cliente que lo recibe.';
                }
            } elseif ($kind === 'combo') {
                $combo = $combos[$item['combo_id']] ?? null;
                if (!$combo || !$combo->active || $combo->items->isEmpty()) {
                    $errors[] = 'El combo elegido no está disponible.';
                }
            } elseif ($kind === 'product') {
                $productNeeds[(int) $item['product_id']] = ($productNeeds[(int) $item['product_id']] ?? 0) + $qty;
            }

            $price = (float) $item['unit_price'];
            $gross = round($qty * $price, 2);
            $manual = min($gross, round((float) ($item['discount'] ?? 0), 2));
            $memberDiscount = $prepaid = 0.0;
            $usesSession = false;
            $clientPackageId = null;
            $badge = null;

            if ($kind === 'service' && $client) {
                $serviceId = (int) $item['service_id'];
                $left = $remaining[$serviceId] ?? 0;
                $fromPackage = $this->packageWithSessions($clientPackages, $packageLeft, $serviceId, $qty);

                if ($membership && $left > 0 && $left >= $qty) {
                    // Sesión incluida en la membresía: la línea queda en 0.
                    $usesSession = true;
                    $memberDiscount = round($gross - $manual, 2);
                    $remaining[$serviceId] = $left - (int) $qty;
                    $badge = 'Sesión incluida (quedan ' . $remaining[$serviceId] . ')';
                } elseif ($fromPackage) {
                    // Sesión prepagada de un paquete: la línea queda en 0.
                    $usesSession = true;
                    $clientPackageId = $fromPackage->id;
                    $prepaid = round($gross - $manual, 2);
                    $packageLeft[$fromPackage->id][$serviceId] -= (int) $qty;
                    $badge = 'Paquete ' . $fromPackage->package_name . ' (quedan ' . $packageLeft[$fromPackage->id][$serviceId] . ')';
                } elseif ($membership && $membership->covers('service', $services[$serviceId]->service_category_id ?? null)) {
                    $memberDiscount = $this->percent($gross - $manual, (float) $membership->discount_percent);
                }
            } elseif ($membership && $kind === 'product' && $membership->covers('product')) {
                $memberDiscount = $this->percent($gross - $manual, (float) $membership->discount_percent);
            }

            if (!$usesSession && $memberDiscount > 0) {
                $badge = '−' . rtrim(rtrim(number_format((float) $membership->discount_percent, 2), '0'), '.') . ' % membresía';
            }

            $total = max(0, round($gross - $manual - $memberDiscount - $prepaid, 2));
            $subtotal += $gross;
            $lineDiscounts += $manual;
            $membershipDiscount += $memberDiscount;
            $packageDiscount += $prepaid;

            $components = [];
            if ($combo) {
                $components = $this->comboComponents($combo, $qty, $gross, $manual);
                foreach ($components as $c) {
                    if ($c['product_id']) {
                        $productNeeds[$c['product_id']] = ($productNeeds[$c['product_id']] ?? 0) + $c['quantity'];
                    }
                }
            }

            $lines[] = [
                'index'                => $i,
                'kind'                 => $kind,
                'service_id'           => $kind === 'service' ? (int) $item['service_id'] : null,
                'product_id'           => $kind === 'product' ? (int) $item['product_id'] : null,
                'membership_plan_id'   => $kind === 'membership' ? (int) $item['membership_plan_id'] : null,
                'service_package_id'   => $kind === 'package' ? (int) $item['service_package_id'] : null,
                'combo_id'             => $kind === 'combo' ? (int) $item['combo_id'] : null,
                'personal_id'          => !empty($item['personal_id']) ? (int) $item['personal_id'] : null,
                'description'          => $item['description'] ?? null,
                'quantity'             => $qty,
                'unit_price'           => $price,
                'gross'                => $gross,
                'discount'             => $manual,
                'membership_discount'  => $memberDiscount,
                'package_discount'     => $prepaid,
                'uses_session'         => $usesSession,
                'client_membership_id' => ($membership && !$clientPackageId && ($usesSession || $memberDiscount > 0)) ? $membership->id : null,
                'client_package_id'    => $clientPackageId,
                'total'                => $total,
                'badge'                => $badge ?? $combo?->summary(),
                'components'           => $components,
            ];
        }

        $errors = array_merge($errors, $this->stockErrors($productNeeds, $companyId));

        $afterLines = max(0, round($subtotal - $lineDiscounts - $membershipDiscount - $packageDiscount, 2));
        $globalDiscount = min($afterLines, round((float) ($data['global_discount'] ?? 0), 2));
        $payable = round($afterLines - $globalDiscount, 2);

        // Canje de puntos: actúa como descuento global (lo asume el salón).
        $points = ['enabled' => $loyaltyOn && (bool) $client];
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

        // Saldo a favor: paga parte (o todo) el total. No cubre la propina,
        // que va íntegra al profesional.
        $creditBalance = $client ? $this->credit->balance($companyId, $client->id) : 0.0;
        $creditAmount = round((float) ($data['credit_amount'] ?? 0), 2);
        $creditApplied = 0.0;
        if ($creditAmount > 0) {
            if (!$client) {
                $errors[] = 'Para usar saldo a favor hay que elegir el cliente.';
            } elseif ($creditAmount > $creditBalance + 0.001) {
                $errors[] = 'El cliente tiene ' . $setting->currency_symbol . ' ' . number_format($creditBalance, 2) . ' de saldo a favor.';
            } elseif ($creditAmount > $total + 0.001) {
                $errors[] = 'El saldo a favor no puede superar el total de la venta.';
            } else {
                $creditApplied = $creditAmount;
            }
        }

        return [
            'lines'               => $lines,
            'subtotal'            => round($subtotal, 2),
            'line_discounts'      => round($lineDiscounts, 2),
            'global_discount'     => $globalDiscount,
            'membership_discount' => round($membershipDiscount, 2),
            'package_discount'    => round($packageDiscount, 2),
            'redeem_points'       => $loyaltyDiscount > 0 ? $redeemPoints : 0,
            'loyalty_discount'    => $loyaltyDiscount,
            'gravado'             => $gravado,
            'tax'                 => $tax,
            'total'               => $total,
            'tip'                 => $tip,
            'credit_applied'      => $creditApplied,
            'charge'              => round($total + $tip - $creditApplied, 2),
            'points_to_earn'      => ($loyaltyOn && $client) ? $setting->pointsFor($total) : 0,
            'client'              => $client ? $this->clientSummary($client, $membership, $points, $creditBalance) : null,
            'errors'              => array_values(array_unique($errors)),
            'warnings'            => $warnings,
        ];
    }

    /**
     * Componentes de un combo con el precio repartido según el precio de
     * lista de cada uno (si ninguno tiene precio, según la cantidad). El
     * último componente absorbe el redondeo para que la suma cuadre.
     */
    public function comboComponents(Combo $combo, float $qty, float $gross, float $discount): array
    {
        $items = $combo->items->values();
        $weights = $items->map(fn ($c) => $c->listPrice() * (float) $c->quantity);
        if ($weights->sum() <= 0) {
            $weights = $items->map(fn ($c) => (float) $c->quantity);
        }
        $totalWeight = max(0.0001, $weights->sum());

        $out = [];
        $grossLeft = $gross;
        $discountLeft = $discount;
        $last = $items->count() - 1;

        foreach ($items as $k => $c) {
            $share = $weights[$k] / $totalWeight;
            $g = $k === $last ? round($grossLeft, 2) : round($gross * $share, 2);
            $d = $k === $last ? round($discountLeft, 2) : round($discount * $share, 2);
            $grossLeft -= $g;
            $discountLeft -= $d;
            $quantity = round((float) $c->quantity * $qty, 2);

            $out[] = [
                'service_id'  => $c->service_id ? (int) $c->service_id : null,
                'product_id'  => $c->product_id ? (int) $c->product_id : null,
                'description' => $c->name() . ' (' . $combo->name . ')',
                'quantity'    => $quantity,
                'unit_price'  => $quantity > 0 ? round($g / $quantity, 2) : 0.0,
                'gross'       => $g,
                'discount'    => $d,
                'total'       => max(0, round($g - $d, 2)),
            ];
        }

        return $out;
    }

    /** Primer paquete del cliente (el que vence antes) con sesiones para el servicio. */
    protected function packageWithSessions($clientPackages, array $packageLeft, int $serviceId, float $qty)
    {
        return $clientPackages->first(fn ($p) => ($packageLeft[$p->id][$serviceId] ?? 0) >= $qty);
    }

    /** Ficha corta del cliente para el POS: membresía, paquetes, puntos y saldo. */
    public function clientSummary(Client $client, $membership = false, ?array $points = null, ?float $creditBalance = null): array
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
            'packages'   => $this->packages->usableFor($client->company_id, $client->id)
                ->map(fn ($p) => $this->packages->summary($p))->values()->all(),
            'points'     => $points,
            'credit'     => $creditBalance ?? $this->credit->balance($client->company_id, $client->id),
        ];
    }

    /** Stock suficiente para todos los productos del carrito (sueltos y en combos). */
    protected function stockErrors(array $needs, int $companyId): array
    {
        if (!$needs) {
            return [];
        }

        return Product::withoutGlobalScopes()->where('company_id', $companyId)->whereIn('id', array_keys($needs))->get()
            ->filter(fn ($p) => $needs[$p->id] > (float) $p->current_stock + 0.0001)
            ->map(fn ($p) => "No hay stock suficiente de {$p->name}: la venta pide "
                . rtrim(rtrim(number_format($needs[$p->id], 2), '0'), '.') . ' y hay '
                . rtrim(rtrim(number_format((float) $p->current_stock, 2), '0'), '.') . '.')
            ->values()->all();
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
            if (self::kindOf($item) === 'custom' && empty($item['description'])) {
                $errors[] = 'Cada línea debe tener un servicio, un producto, un combo, un paquete, una membresía o una descripción.';
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
            [ServicePackage::class, $items->pluck('service_package_id')],
            [Combo::class, $items->pluck('combo_id')],
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
