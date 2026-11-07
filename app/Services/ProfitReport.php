<?php

namespace App\Services;

use App\Models\CashMovement;
use App\Models\ExpenseCategory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Reporte de ganancias: cuánto queda de lo vendido después del costo de los
 * productos y las comisiones. Lo usan la web y la API.
 *
 * Reglas:
 *  - Una venta puede traer productos y servicios: se reparte línea por línea.
 *    Lo que no es producto (servicios, membresías, paquetes) va en Servicios.
 *  - Los descuentos sobre el total, los impuestos y las devoluciones se reparten
 *    entre las líneas en proporción, para que la suma coincida con la venta.
 *  - El costo es el costo actual del producto (no se guarda el de cada venta).
 *  - Todo cuenta en la fecha de la venta. La ganancia neta resta, además, los
 *    gastos operativos del período (sin proveedores, que ya entran como costo,
 *    comisiones, propinas, devoluciones ni traspasos).
 */
class ProfitReport
{
    public const SCOPES = ['ambos', 'productos', 'servicios'];

    /** Excluidos de los gastos operativos: ya cuentan por otro lado o no son del negocio. */
    private const NOT_OPERATING = ['Propinas', 'Comisiones', 'Pago a proveedores', 'Devoluciones', CashMovement::CATEGORY_TRANSFER];

    public function __construct(protected int $companyId, protected Carbon $from, protected Carbon $to, protected string $scope = 'ambos')
    {
        $this->from = $from->copy()->startOfDay();
        $this->to = $to->copy()->endOfDay();
    }

    /** @return array{0: Carbon, 1: Carbon}|null */
    public static function presetRange(?string $preset): ?array
    {
        $today = today();
        $week = $today->copy()->startOfWeek(Carbon::MONDAY);
        $month = $today->copy()->startOfMonth();

        return match ($preset) {
            'hoy'        => [$today, $today],
            'semana'     => [$week, $today],
            'semana_ant' => [$week->copy()->subWeek(), $week->copy()->subDay()],
            'mes'        => [$month, $today],
            'mes_ant'    => [$month->copy()->subMonthNoOverflow(), $month->copy()->subDay()],
            default      => null,
        };
    }

    public function build(): array
    {
        $sales = DB::table('sales as s')->leftJoin('clients as c', 'c.id', '=', 's.client_id')
            ->where('s.company_id', $this->companyId)->where('s.status', 'completada')->whereNull('s.deleted_at')
            ->whereBetween('s.sale_date', [$this->from, $this->to])
            ->orderByDesc('s.sale_date')->orderByDesc('s.id')
            ->get(['s.id', 's.sale_number', 's.sale_date', 's.created_at', 's.total', 's.returned_total', 's.client_name', 'c.full_name']);

        $lines = DB::table('sale_details as d')->join('sales as s', 's.id', '=', 'd.sale_id')
            ->leftJoin('products as p', 'p.id', '=', 'd.product_id')
            ->leftJoin('services as v', 'v.id', '=', 'd.service_id')
            ->where('s.company_id', $this->companyId)->where('s.status', 'completada')->whereNull('s.deleted_at')->whereNull('d.deleted_at')
            ->whereBetween('s.sale_date', [$this->from, $this->to])
            ->get(['d.id', 'd.sale_id', 'd.product_id', 'd.service_id', 'd.description', 'd.quantity', 'd.returned_quantity', 'd.total', 'p.name as product_name', 'p.cost as product_cost', 'v.name as service_name'])
            ->groupBy('sale_id');

        $commissions = DB::table('commissions as m')->join('sales as s', 's.id', '=', 'm.sale_id')
            ->where('s.company_id', $this->companyId)->where('s.status', 'completada')->whereNull('s.deleted_at')
            ->whereBetween('s.sale_date', [$this->from, $this->to])
            ->groupBy('m.sale_id', 'm.sale_detail_id')
            ->get(['m.sale_id', 'm.sale_detail_id', DB::raw('SUM(m.amount) as amount')])
            ->groupBy('sale_id');

        $empty = fn () => ['revenue' => 0.0, 'cost' => 0.0, 'commissions' => 0.0, 'count' => 0];
        $buckets = ['productos' => $empty(), 'servicios' => $empty()];
        $ranks = ['productos' => [], 'servicios' => []];
        $days = [];
        $rows = [];

        foreach ($sales as $sale) {
            $own = $this->saleBuckets($sale, $lines->get($sale->id, collect()), $commissions->get($sale->id, collect()), $ranks);

            $shown = ['revenue' => 0.0, 'cost' => 0.0];
            foreach ($own as $bucket => $values) {
                if (!$values['has']) {
                    continue;
                }
                $buckets[$bucket]['revenue'] += $values['revenue'];
                $buckets[$bucket]['cost'] += $values['cost'];
                $buckets[$bucket]['commissions'] += $values['commissions'];
                $buckets[$bucket]['count']++;
                if ($this->scope === 'ambos' || $this->scope === $bucket) {
                    $shown['revenue'] += $values['revenue'];
                    $shown['cost'] += $values['cost'] + $values['commissions'];
                }
            }

            $inScope = $this->scope === 'ambos' || $own[$this->scope]['has'];
            if (!$inScope) {
                continue;
            }

            $day = substr((string) $sale->sale_date, 0, 10);
            $days[$day] ??= ['date' => $day, 'sold' => 0.0, 'profit' => 0.0];
            $days[$day]['sold'] += $shown['revenue'];
            $days[$day]['profit'] += $shown['revenue'] - $shown['cost'];

            $rows[] = [
                'number' => $sale->sale_number,
                'at'     => $sale->created_at ? Carbon::parse($sale->created_at)->format('Y-m-d H:i') : $day,
                'client' => $sale->full_name ?: ($sale->client_name ?: 'Sin cliente'),
                'sold'   => round($shown['revenue'], 2),
                'cost'   => round($shown['cost'], 2),
                'profit' => round($shown['revenue'] - $shown['cost'], 2),
                'margin' => $this->margin($shown['revenue'] - $shown['cost'], $shown['revenue']),
            ];
        }

        $picked = $this->scope === 'ambos' ? array_values($buckets) : [$buckets[$this->scope]];
        $revenue = array_sum(array_column($picked, 'revenue'));
        $cost = array_sum(array_column($picked, 'cost')) + array_sum(array_column($picked, 'commissions'));
        $profit = $revenue - $cost;
        krsort($days);

        return [
            'scope'    => $this->scope,
            'from'     => $this->from->toDateString(),
            'to'       => $this->to->toDateString(),
            'summary'  => ['revenue' => round($revenue, 2), 'cost' => round($cost, 2), 'profit' => round($profit, 2), 'margin' => $this->margin($profit, $revenue)],
            'products' => $this->bucketRow($buckets['productos']),
            'services' => $this->bucketRow($buckets['servicios']),
            'net'      => $this->scope === 'ambos' ? $this->net($profit) : null,
            'days'     => array_map(fn ($d) => ['date' => $d['date'], 'sold' => round($d['sold'], 2), 'profit' => round($d['profit'], 2)], array_values($days)),
            'product_rank' => $this->scope === 'servicios' ? null : $this->rank($ranks['productos']),
            'service_rank' => $this->scope === 'productos' ? null : $this->rank($ranks['servicios']),
            'sales'       => array_slice($rows, 0, 50),
            'sales_count' => count($rows),
            'note'        => 'El costo usa el costo actual de cada producto. La ganancia descuenta el costo de los productos y las comisiones.',
        ];
    }

    /** Reparte una venta entre productos y servicios, y suma a los rankings. */
    protected function saleBuckets(object $sale, $lines, $commissions, array &$ranks): array
    {
        $net = (float) $sale->total - (float) $sale->returned_total;
        $weight = fn ($l) => (float) $l->quantity > 0 ? (float) $l->total * ((float) $l->quantity - (float) $l->returned_quantity) / (float) $l->quantity : 0.0;
        $weights = $lines->sum($weight);
        // Lo que diferencia las líneas del total: descuento global, cupón, puntos, impuesto.
        $factor = $weights > 0 ? $net / $weights : 0.0;
        $byDetail = $commissions->whereNotNull('sale_detail_id')->pluck('amount', 'sale_detail_id');

        $own = [
            'productos' => ['revenue' => 0.0, 'cost' => 0.0, 'commissions' => 0.0, 'has' => false],
            'servicios' => ['revenue' => 0.0, 'cost' => 0.0, 'commissions' => 0.0, 'has' => false],
        ];

        foreach ($lines as $line) {
            $bucket = $line->product_id ? 'productos' : 'servicios';
            $qty = (float) $line->quantity - (float) $line->returned_quantity;
            $revenue = $weight($line) * $factor;
            $cost = $line->product_id ? $qty * (float) $line->product_cost : 0.0;
            $commission = (float) ($byDetail[$line->id] ?? 0);

            $own[$bucket]['revenue'] += $revenue;
            $own[$bucket]['cost'] += $cost;
            $own[$bucket]['commissions'] += $commission;
            $own[$bucket]['has'] = true;

            $name = $line->product_name ?: ($line->service_name ?: ($line->description ?: 'N/D'));
            $ranks[$bucket][$name] ??= ['name' => $name, 'qty' => 0.0, 'sold' => 0.0, 'profit' => 0.0];
            $ranks[$bucket][$name]['qty'] += $qty;
            $ranks[$bucket][$name]['sold'] += $revenue;
            $ranks[$bucket][$name]['profit'] += $revenue - $cost - $commission;
        }

        // Venta sin líneas, o comisión sin línea: va a Servicios.
        if ($lines->isEmpty() && $net != 0.0) {
            $own['servicios']['revenue'] += $net;
            $own['servicios']['has'] = true;
        }
        $loose = (float) $commissions->filter(fn ($c) => !$lines->contains('id', $c->sale_detail_id))->sum('amount');
        if ($loose != 0.0) {
            $own['servicios']['commissions'] += $loose;
            $own['servicios']['has'] = true;
        }

        return $own;
    }

    protected function bucketRow(array $b): array
    {
        $profit = $b['revenue'] - $b['cost'] - $b['commissions'];

        return [
            'revenue'     => round($b['revenue'], 2),
            'cost'        => round($b['cost'], 2),
            'commissions' => round($b['commissions'], 2),
            'profit'      => round($profit, 2),
            'margin'      => $this->margin($profit, $b['revenue']),
            'count'       => $b['count'],
        ];
    }

    /** Los 10 que más dejan y los 10 de menor margen. */
    protected function rank(array $items): array
    {
        $rows = collect($items)->map(fn ($r) => [
            'name'   => $r['name'],
            'qty'    => round($r['qty'], 2),
            'sold'   => round($r['sold'], 2),
            'profit' => round($r['profit'], 2),
            'margin' => $this->margin($r['profit'], $r['sold']),
        ])->values();

        return [
            'top' => $rows->sortByDesc('profit')->take(10)->values()->all(),
            'low' => $rows->where('sold', '>', 0)->sortBy('margin')->take(10)->values()->all(),
        ];
    }

    /** Ganancia después de los gastos operativos del período. */
    protected function net(float $profit): array
    {
        $supplierCategories = ExpenseCategory::forCompany($this->companyId)->where('payee', 'proveedor')->where('recurring', false)->pluck('id');
        $expenses = (float) CashMovement::forCompany($this->companyId)->live()->where('type', 'egreso')
            ->whereBetween('movement_date', [$this->from->toDateString(), $this->to->toDateString()])
            ->where(fn ($q) => $q->whereNull('category')->orWhereNotIn('category', self::NOT_OPERATING))
            ->where(fn ($q) => $q->whereNull('expense_category_id')->orWhereNotIn('expense_category_id', $supplierCategories))
            ->where('concept', 'not like', 'Anulación%')
            ->sum('amount');

        return ['expenses' => round($expenses, 2), 'profit' => round($profit - $expenses, 2)];
    }

    protected function margin(float $profit, float $revenue): float
    {
        return $revenue > 0 ? round($profit * 100 / $revenue, 1) : 0.0;
    }
}
