<?php

namespace App\Services;

use App\Models\ClientPackage;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\ServicePackage;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Paquetes de sesiones prepagadas: cuáles puede usar el cliente, cuántas
 * sesiones le quedan, alta al venderse y cancelación.
 *
 * Reglas de Glamora:
 *  - Un cliente puede tener varios paquetes a la vez. Al cobrar un servicio
 *    se usa primero el que vence antes (los que no vencen, al final).
 *  - Las sesiones usadas no se guardan: se cuentan desde las líneas de venta
 *    no anuladas (descontando lo devuelto), así anular o devolver una venta
 *    devuelve la sesión.
 *  - Cada sesión vale lo que se cobró por ella: el precio pagado repartido
 *    según el precio de lista de cada servicio. Es la base de la comisión.
 */
class PackageService
{
    /** Paquetes que el cliente puede usar hoy, el que vence antes primero. */
    public function usableFor(int $companyId, int $clientId): Collection
    {
        $today = today()->toDateString();

        return ClientPackage::withoutGlobalScopes()
            ->with('items.service')
            ->where('company_id', $companyId)
            ->where('client_id', $clientId)
            ->where('status', 'active')
            ->where('starts_on', '<=', $today)
            ->where(fn ($q) => $q->whereNull('expires_on')->orWhere('expires_on', '>=', $today))
            ->orderByRaw('expires_on IS NULL, expires_on, id')
            ->get()
            ->filter(fn (ClientPackage $p) => array_sum($this->remaining($p)) > 0)
            ->values();
    }

    /** service_id => sesiones usadas (ventas no anuladas, menos lo devuelto). */
    public function used(ClientPackage $package): array
    {
        return SaleItem::query()
            ->join('sales', 'sales.id', '=', 'sale_details.sale_id')
            ->where('sale_details.client_package_id', $package->id)
            ->where('sale_details.uses_session', true)
            ->where('sales.status', '!=', 'anulada')
            ->whereNull('sales.deleted_at')
            ->groupBy('sale_details.service_id')
            ->selectRaw('sale_details.service_id, SUM(sale_details.quantity - sale_details.returned_quantity) AS used')
            ->pluck('used', 'service_id')
            ->map(fn ($v) => (int) round((float) $v))
            ->all();
    }

    /** service_id => sesiones que quedan. */
    public function remaining(ClientPackage $package): array
    {
        $used = $this->used($package);

        return $package->items
            ->mapWithKeys(fn ($i) => [(int) $i->service_id => max(0, $i->included - ($used[$i->service_id] ?? 0))])
            ->all();
    }

    /** Resumen para el POS y la ficha del cliente. */
    public function summary(ClientPackage $package): array
    {
        $used = $this->used($package);
        $left = 0;

        $items = $package->items->map(function ($i) use ($used, &$left) {
            $remaining = max(0, $i->included - ($used[$i->service_id] ?? 0));
            $left += $remaining;

            return [
                'service_id' => (int) $i->service_id,
                'service'    => $i->service?->name ?? 'Servicio eliminado',
                'included'   => $i->included,
                'used'       => min($i->included, $used[$i->service_id] ?? 0),
                'remaining'  => $remaining,
                'unit_value' => (float) $i->unit_value,
            ];
        })->values()->all();

        $state = $package->state($left);

        return [
            'id'          => $package->id,
            'name'        => $package->package_name,
            'expires_on'  => $package->expires_on?->format('d/m/Y'),
            'expiry'      => $package->expiryLabel(),
            'price_paid'  => (float) $package->price_paid,
            'remaining'   => $left,
            'state'       => $state,
            'state_label' => ClientPackage::STATES[$state][0],
            'state_badge' => ClientPackage::STATES[$state][1],
            'items'       => $items,
        ];
    }

    /** Da de alta el paquete vendido en una línea del POS. */
    public function activateFromSale(Sale $sale, SaleItem $item, ServicePackage $package): ClientPackage
    {
        $package->loadMissing('items.service');
        $pricePaid = round((float) $item->total, 2);

        $clientPackage = ClientPackage::create([
            'company_id'         => $sale->company_id,
            'client_id'          => $sale->client_id,
            'service_package_id' => $package->id,
            'sale_id'            => $sale->id,
            'sale_detail_id'     => $item->id,
            'package_name'       => $package->name,
            'price_paid'         => $pricePaid,
            'starts_on'          => today()->toDateString(),
            'expires_on'         => $package->validity_days ? today()->addDays($package->validity_days - 1)->toDateString() : null,
            'status'             => 'active',
            'created_by'         => auth()->id(),
        ]);

        foreach ($this->unitValues($package, $pricePaid) as $serviceId => [$included, $unitValue]) {
            $clientPackage->items()->create([
                'service_id' => $serviceId,
                'included'   => $included,
                'unit_value' => $unitValue,
            ]);
        }

        return $clientPackage;
    }

    /**
     * Reparte el precio pagado entre las sesiones según el precio de lista
     * de cada servicio. Si ningún servicio tiene precio, en partes iguales.
     *
     * @return array<int, array{0:int, 1:float}> service_id => [sesiones, valor por sesión]
     */
    public function unitValues(ServicePackage $package, float $pricePaid): array
    {
        $weights = $package->items->mapWithKeys(fn ($i) => [(int) $i->service_id => (float) ($i->service?->price ?? 0) * $i->quantity]);
        $totalWeight = $weights->sum();
        $sessions = max(1, $package->totalSessions());

        return $package->items->mapWithKeys(function ($i) use ($weights, $totalWeight, $pricePaid, $sessions) {
            $share = $totalWeight > 0
                ? $pricePaid * $weights[(int) $i->service_id] / $totalWeight
                : $pricePaid * $i->quantity / $sessions;

            return [(int) $i->service_id => [(int) $i->quantity, $i->quantity > 0 ? round($share / $i->quantity, 2) : 0.0]];
        })->all();
    }

    /** Cancela los paquetes vendidos en una venta (anulación). Devuelve cuántos. */
    public function cancelForVoidedSale(Sale $sale): int
    {
        return ClientPackage::withoutGlobalScopes()
            ->where('sale_id', $sale->id)
            ->where('status', 'active')
            ->update(['status' => 'canceled', 'canceled_at' => now()]);
    }

    /** Cancela el paquete vendido en una línea (devolución). */
    public function cancelForLine(SaleItem $item, string $reason): int
    {
        return ClientPackage::withoutGlobalScopes()
            ->where('sale_detail_id', $item->id)
            ->where('status', 'active')
            ->get()
            ->each(fn (ClientPackage $p) => $this->cancel($p, $reason))
            ->count();
    }

    public function cancel(ClientPackage $package, ?string $reason = null): void
    {
        DB::transaction(fn () => $package->update([
            'status'      => 'canceled',
            'canceled_at' => now(),
            'notes'       => trim(($package->notes ? $package->notes . "\n" : '') . ($reason ? 'Cancelado: ' . $reason : 'Cancelado')),
        ]));
    }
}
