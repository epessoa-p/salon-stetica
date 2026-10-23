<?php

namespace App\Services;

use App\Models\ClientMembership;
use App\Models\MembershipPlan;
use App\Models\Sale;
use App\Models\SaleItem;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Membresías de clientes: cuál está vigente, cuántas sesiones le quedan,
 * alta al venderse en el POS y cancelación al anular la venta.
 *
 * Reglas de Glamora:
 *  - Una membresía vigente por cliente. Comprar otra estando vigente la
 *    encadena: empieza el día siguiente al vencimiento actual.
 *  - Las sesiones usadas no se guardan: se cuentan desde las líneas de venta
 *    no anuladas que las consumieron, así anular una venta devuelve la sesión.
 */
class MembershipService
{
    /** Membresía vigente del cliente en una fecha (por defecto, hoy). */
    public function activeFor(int $companyId, int $clientId, ?Carbon $on = null): ?ClientMembership
    {
        $day = ($on ?? today())->toDateString();

        return ClientMembership::withoutGlobalScopes()
            ->with(['sessions.service', 'category'])
            ->where('company_id', $companyId)
            ->where('client_id', $clientId)
            ->where('status', 'active')
            ->where('starts_on', '<=', $day)
            ->where('ends_on', '>=', $day)
            ->orderByDesc('ends_on')
            ->first();
    }

    /** service_id => sesiones ya usadas (líneas de ventas no anuladas). */
    public function usedSessions(ClientMembership $membership): array
    {
        return SaleItem::query()
            ->join('sales', 'sales.id', '=', 'sale_details.sale_id')
            ->where('sale_details.client_membership_id', $membership->id)
            ->where('sale_details.uses_session', true)
            ->where('sales.status', '!=', 'anulada')
            ->whereNull('sales.deleted_at')
            ->groupBy('sale_details.service_id')
            ->selectRaw('sale_details.service_id, SUM(sale_details.quantity) AS used')
            ->pluck('used', 'service_id')
            ->map(fn ($v) => (int) round((float) $v))
            ->all();
    }

    /** service_id => sesiones que quedan. */
    public function remainingSessions(ClientMembership $membership): array
    {
        $used = $this->usedSessions($membership);

        return $membership->sessions
            ->mapWithKeys(fn ($s) => [(int) $s->service_id => max(0, $s->included - ($used[$s->service_id] ?? 0))])
            ->all();
    }

    /** Resumen listo para la ficha y el POS. */
    public function summary(ClientMembership $membership): array
    {
        $used = $this->usedSessions($membership);

        return [
            'id'         => $membership->id,
            'plan'       => $membership->plan_name,
            'starts_on'  => $membership->starts_on->format('d/m/Y'),
            'ends_on'    => $membership->ends_on->format('d/m/Y'),
            'days_left'  => $membership->daysLeft(),
            'discount'   => (float) $membership->discount_percent,
            'scope'      => $membership->scopeLabel(),
            'sessions'   => $membership->sessions->map(fn ($s) => [
                'service_id' => (int) $s->service_id,
                'service'    => $s->service?->name ?? 'Servicio eliminado',
                'included'   => $s->included,
                'used'       => min($s->included, $used[$s->service_id] ?? 0),
                'remaining'  => max(0, $s->included - ($used[$s->service_id] ?? 0)),
            ])->values()->all(),
        ];
    }

    /**
     * Da de alta la membresía vendida en una línea del POS. Si el cliente ya
     * tiene una vigente (o programada), la nueva empieza al día siguiente.
     */
    public function activateFromSale(Sale $sale, SaleItem $item, MembershipPlan $plan): ClientMembership
    {
        $plan->loadMissing('sessions');

        $lastEnd = ClientMembership::withoutGlobalScopes()
            ->where('company_id', $sale->company_id)
            ->where('client_id', $sale->client_id)
            ->where('status', 'active')
            ->where('ends_on', '>=', today()->toDateString())
            ->max('ends_on');

        $starts = $lastEnd ? Carbon::parse($lastEnd)->addDay() : today();
        $pricePaid = round((float) $item->total, 2);
        $totalSessions = $plan->totalSessions();

        $membership = ClientMembership::create([
            'company_id'          => $sale->company_id,
            'client_id'           => $sale->client_id,
            'membership_plan_id'  => $plan->id,
            'sale_id'             => $sale->id,
            'sale_detail_id'      => $item->id,
            'plan_name'           => $plan->name,
            'price_paid'          => $pricePaid,
            'discount_percent'    => $plan->discount_percent,
            'discount_scope'      => $plan->discount_scope,
            'service_category_id' => $plan->service_category_id,
            // Lo que el salón cobró por cada sesión: base de la comisión al usarla.
            'session_unit_value'  => $totalSessions > 0 ? round($pricePaid / $totalSessions, 2) : 0,
            'starts_on'           => $starts->toDateString(),
            'ends_on'             => $starts->copy()->addDays(max(1, $plan->duration_days) - 1)->toDateString(),
            'status'              => 'active',
            'created_by'          => auth()->id(),
        ]);

        foreach ($plan->sessions as $session) {
            $membership->sessions()->create([
                'service_id' => $session->service_id,
                'included'   => $session->quantity,
            ]);
        }

        return $membership;
    }

    /** Cancela las membresías vendidas en una venta anulada. Devuelve cuántas. */
    public function cancelForVoidedSale(Sale $sale): int
    {
        return ClientMembership::withoutGlobalScopes()
            ->where('sale_id', $sale->id)
            ->where('status', 'active')
            ->update(['status' => 'canceled', 'canceled_at' => now()]);
    }

    public function cancel(ClientMembership $membership, ?string $reason = null): void
    {
        DB::transaction(fn () => $membership->update([
            'status'      => 'canceled',
            'canceled_at' => now(),
            'notes'       => trim(($membership->notes ? $membership->notes . "\n" : '') . ($reason ? 'Cancelada: ' . $reason : 'Cancelada')),
        ]));
    }
}
