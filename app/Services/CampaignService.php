<?php

namespace App\Services;

use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Models\Client;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Segmentos de clientes para campañas. Siempre clientes activos con teléfono.
 *
 * Filtros (todos opcionales, se combinan con "y"):
 *  - inactive_days: su última visita (cita completada o compra) fue hace más de N días
 *  - service_id: se hizo ese servicio (cita completada o compra) en los últimos service_days días
 *  - membership: with | without (membresía vigente hoy)
 *  - birthday_month: cumple en ese mes (1-12)
 *  - min_spent: gastó al menos ese monto (ventas no anuladas, neto de devoluciones)
 */
class CampaignService
{
    public const FILTER_KEYS = ['inactive_days', 'service_id', 'service_days', 'membership', 'birthday_month', 'min_spent'];

    public function query(int $companyId, array $filters): Builder
    {
        $f = array_filter(array_intersect_key($filters, array_flip(self::FILTER_KEYS)), fn ($v) => $v !== null && $v !== '');
        $today = today()->toDateString();

        $lastVisit = '(SELECT MAX(x.d) FROM ('
            . "SELECT a.starts_at AS d, a.client_id FROM appointments a WHERE a.status = 'completada' AND a.deleted_at IS NULL"
            . " UNION ALL SELECT s.sale_date AS d, s.client_id FROM sales s WHERE s.status != 'anulada' AND s.deleted_at IS NULL"
            . ') x WHERE x.client_id = clients.id)';

        return Client::withoutGlobalScopes()
            ->where('clients.company_id', $companyId)
            ->where('clients.active', true)
            ->whereNull('clients.deleted_at')
            ->whereNotNull('clients.phone')->where('clients.phone', '!=', '')
            ->when(isset($f['inactive_days']), fn ($q) => $q
                ->whereRaw("$lastVisit IS NOT NULL")
                ->whereRaw("$lastVisit < ?", [today()->subDays((int) $f['inactive_days'])->toDateString()]))
            ->when(isset($f['service_id']), function ($q) use ($f) {
                $since = today()->subDays((int) ($f['service_days'] ?? 365))->toDateString();
                $q->where(fn ($w) => $w
                    ->whereExists(fn ($e) => $e->select(DB::raw(1))->from('appointments')->whereColumn('appointments.client_id', 'clients.id')
                        ->where('appointments.service_id', $f['service_id'])->where('appointments.status', 'completada')->whereNull('appointments.deleted_at')
                        ->where('appointments.starts_at', '>=', $since))
                    ->orWhereExists(fn ($e) => $e->select(DB::raw(1))->from('sale_details')->join('sales', 'sales.id', '=', 'sale_details.sale_id')
                        ->whereColumn('sales.client_id', 'clients.id')->where('sale_details.service_id', $f['service_id'])
                        ->where('sales.status', '!=', 'anulada')->whereNull('sales.deleted_at')->where('sales.sale_date', '>=', $since)));
            })
            ->when(($f['membership'] ?? null) === 'with', fn ($q) => $q->whereExists($this->activeMembership($today)))
            ->when(($f['membership'] ?? null) === 'without', fn ($q) => $q->whereNotExists($this->activeMembership($today)))
            ->when(isset($f['birthday_month']), fn ($q) => $q->whereNotNull('clients.birthdate')->whereMonth('clients.birthdate', (int) $f['birthday_month']))
            ->when(isset($f['min_spent']), fn ($q) => $q->whereRaw(
                "(SELECT COALESCE(SUM(s.total - s.returned_total), 0) FROM sales s WHERE s.client_id = clients.id AND s.status != 'anulada' AND s.deleted_at IS NULL) >= ?",
                [(float) $f['min_spent']]))
            ->orderBy('clients.full_name');
    }

    /** Crea la campaña con la lista de destinatarios congelada en este momento. */
    public function create(int $companyId, array $data): Campaign
    {
        return DB::transaction(function () use ($companyId, $data) {
            $campaign = Campaign::create([
                'company_id' => $companyId,
                'name'       => $data['name'],
                'body'       => $data['body'],
                'filters'    => $data['filters'],
                'coupon_id'  => $data['coupon_id'] ?? null,
                'status'     => 'active',
                'created_by' => auth()->id(),
            ]);

            $this->query($companyId, $data['filters'])->pluck('clients.id')
                ->chunk(500)
                ->each(fn ($ids) => CampaignRecipient::insert($ids->map(fn ($id) => [
                    'campaign_id' => $campaign->id, 'client_id' => $id, 'created_at' => now(), 'updated_at' => now(),
                ])->all()));

            return $campaign;
        });
    }

    /** Descripción legible del segmento. */
    public function describe(array $filters, array $serviceNames = []): string
    {
        $parts = [];
        if (!empty($filters['inactive_days'])) {
            $parts[] = 'sin venir hace más de ' . (int) $filters['inactive_days'] . ' días';
        }
        if (!empty($filters['service_id'])) {
            $parts[] = 'se hicieron ' . ($serviceNames[$filters['service_id']] ?? 'el servicio') . ' en los últimos ' . (int) ($filters['service_days'] ?? 365) . ' días';
        }
        if (($filters['membership'] ?? '') === 'with') {
            $parts[] = 'con membresía vigente';
        } elseif (($filters['membership'] ?? '') === 'without') {
            $parts[] = 'sin membresía';
        }
        if (!empty($filters['birthday_month'])) {
            $parts[] = 'cumplen en ' . \Illuminate\Support\Carbon::create(null, (int) $filters['birthday_month'], 1)->locale('es')->isoFormat('MMMM');
        }
        if (!empty($filters['min_spent'])) {
            $parts[] = 'gastaron al menos ' . number_format((float) $filters['min_spent'], 2);
        }

        return $parts ? 'Clientes que ' . implode(', ', $parts) : 'Todos los clientes con teléfono';
    }

    protected function activeMembership(string $today): \Closure
    {
        return fn ($e) => $e->select(DB::raw(1))->from('client_memberships')->whereColumn('client_memberships.client_id', 'clients.id')
            ->where('client_memberships.status', 'active')->where('client_memberships.starts_on', '<=', $today)->where('client_memberships.ends_on', '>=', $today);
    }
}
