<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Análisis comparativo de la app: por pestaña (servicios, ventas, compras,
 * personal, top), siempre con monto y cantidad.
 *
 * Reglas:
 *  - La semana empieza el lunes. Los indicadores comparan lo que va del período
 *    contra el mismo tramo del anterior (lunes a hoy contra lunes al mismo día
 *    de la semana pasada), para no comparar una semana a medias con una entera.
 *  - Ventas y servicios son netos de devoluciones. Compras cuenta las órdenes
 *    recibidas, netas de devoluciones a proveedores.
 */
class AnalysisReport
{
    private const WEEKDAYS = ['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'];
    private const MONTHS = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];

    protected Carbon $today;
    protected Carbon $weekStart;
    protected Carbon $prevWeekStart;
    protected Carbon $monthStart;
    protected Carbon $prevMonthStart;
    protected Carbon $prevMonthTo;

    public function __construct(protected int $companyId)
    {
        $this->today = today();
        $this->weekStart = $this->today->copy()->startOfWeek(Carbon::MONDAY);
        $this->prevWeekStart = $this->weekStart->copy()->subWeek();
        $this->monthStart = $this->today->copy()->startOfMonth();
        $this->prevMonthStart = $this->monthStart->copy()->subMonthNoOverflow();
        // Mismo día del mes anterior; si no existe (31 de marzo), su último día.
        $this->prevMonthTo = $this->prevMonthStart->copy()->day(min($this->today->day, $this->prevMonthStart->daysInMonth));
    }

    /** Servicios, ventas o compras: indicadores y las tres comparativas. */
    public function series(string $tab): array
    {
        // Hasta 8 semanas o 6 meses atrás, lo que llegue más lejos.
        $from = $this->today->copy()->startOfMonth()->subMonthsNoOverflow(5)->min($this->weekStart->copy()->subWeeks(7));
        $daily = match ($tab) {
            'servicios' => $this->dailyServices($from),
            'compras'   => $this->dailyPurchases($from),
            default     => $this->dailySales($from),
        };

        $result = [
            'kpis'   => $this->kpis($daily),
            'by_day' => $this->byDay($daily),
            'weeks'  => $this->weeks($daily),
            'months' => $this->months($daily),
        ];

        if ($tab === 'ventas') {
            $result['hours'] = $this->hours();
        }

        return $result;
    }

    /** Cada profesional: lo vendido y sus servicios, en semana y mes contra el anterior. */
    public function personal(bool $withCommissions): array
    {
        $from = $this->prevMonthStart->copy()->min($this->prevWeekStart);

        $rows = $this->saleLines($from)
            ->whereNotNull('d.personal_id')
            ->selectRaw('d.personal_id as pid, DATE(s.sale_date) as day')
            ->selectRaw('SUM(CASE WHEN d.quantity > 0 THEN d.total * (d.quantity - d.returned_quantity) / d.quantity ELSE 0 END) as amount')
            ->selectRaw('SUM(CASE WHEN d.service_id IS NOT NULL THEN d.quantity - d.returned_quantity ELSE 0 END) as qty')
            ->groupBy('pid', 'day')->get()->groupBy('pid');

        $names = DB::table('personals')->where('company_id', $this->companyId)->whereIn('id', $rows->keys())->pluck('full_name', 'id');
        $commissions = $withCommissions
            ? DB::table('commissions')->where('company_id', $this->companyId)->whereBetween('accrued_at', [$this->monthStart, $this->today->copy()->endOfDay()])
                ->groupBy('personal_id')->selectRaw('personal_id, SUM(amount) as total')->pluck('total', 'personal_id')
            : collect();

        $people = $rows->map(function (Collection $days, $pid) use ($names, $commissions, $withCommissions) {
            $daily = $days->mapWithKeys(fn ($r) => [$r->day => ['amount' => (float) $r->amount, 'count' => (float) $r->qty]])->all();

            return [
                'id'          => (int) $pid,
                'name'        => $names[$pid] ?? 'N/D',
                'week'        => $this->comparison($daily, $this->weekStart, $this->today, $this->prevWeekStart, $this->prevWeekStart->copy()->addDays($this->weekStart->diffInDays($this->today))),
                'month'       => $this->comparison($daily, $this->monthStart, $this->today, $this->prevMonthStart, $this->prevMonthTo),
                'commissions' => $withCommissions ? round((float) ($commissions[$pid] ?? 0), 2) : null,
            ];
        })->sortByDesc(fn ($p) => $p['month']['amount'])->values();

        $sum = fn (string $period) => [
            'amount'      => round($people->sum("$period.amount"), 2),
            'count'       => round($people->sum("$period.count"), 2),
            'prev_amount' => round($people->sum("$period.prev_amount"), 2),
            'prev_count'  => round($people->sum("$period.prev_count"), 2),
        ];

        return ['kpis' => ['week' => $sum('week'), 'month' => $sum('month')], 'people' => $people->all()];
    }

    /** Lo más vendido y los mejores clientes, de la semana y del mes. */
    public function top(): array
    {
        $build = fn (Carbon $from) => [
            'services' => $this->topLines($from, 'service_id', 'services', 'name'),
            'products' => $this->topLines($from, 'product_id', 'products', 'name'),
            'clients'  => DB::table('sales as s')->join('clients as c', 'c.id', '=', 's.client_id')
                ->where('s.company_id', $this->companyId)->where('s.status', 'completada')->whereNull('s.deleted_at')
                ->whereBetween('s.sale_date', [$from, $this->today->copy()->endOfDay()])
                ->groupBy('c.id', 'c.full_name')
                ->selectRaw('c.full_name as name, SUM(s.total - s.returned_total) as amount, COUNT(*) as qty')
                ->orderByDesc('amount')->limit(10)->get()->map(fn ($r) => $this->topRow($r))->all(),
        ];

        return ['week' => $build($this->weekStart), 'month' => $build($this->monthStart)];
    }

    // ---- Series por día: [Y-m-d => [amount, count]] ----

    protected function dailySales(Carbon $from): array
    {
        $daily = DB::table('sales')->where('company_id', $this->companyId)->where('status', 'completada')->whereNull('deleted_at')
            ->whereBetween('sale_date', [$from, $this->today->copy()->endOfDay()])
            ->groupByRaw('DATE(sale_date)')
            ->selectRaw('DATE(sale_date) as day, SUM(total) as amount, COUNT(*) as qty')->get()
            ->mapWithKeys(fn ($r) => [$r->day => ['amount' => (float) $r->amount, 'count' => (float) $r->qty]])->all();

        return $this->minus($daily, 'sale_returns', $from);
    }

    protected function dailyServices(Carbon $from): array
    {
        return $this->saleLines($from)->whereNotNull('d.service_id')
            ->groupByRaw('DATE(s.sale_date)')
            ->selectRaw('DATE(s.sale_date) as day')
            ->selectRaw('SUM(CASE WHEN d.quantity > 0 THEN d.total * (d.quantity - d.returned_quantity) / d.quantity ELSE 0 END) as amount')
            ->selectRaw('SUM(d.quantity - d.returned_quantity) as qty')->get()
            ->mapWithKeys(fn ($r) => [$r->day => ['amount' => (float) $r->amount, 'count' => (float) $r->qty]])->all();
    }

    protected function dailyPurchases(Carbon $from): array
    {
        $daily = DB::table('entries')->where('company_id', $this->companyId)->where('status', 'recibida')->whereNull('deleted_at')
            ->whereBetween('entry_date', [$from, $this->today->copy()->endOfDay()])
            ->groupByRaw('DATE(entry_date)')
            ->selectRaw('DATE(entry_date) as day, SUM(total) as amount, COUNT(*) as qty')->get()
            ->mapWithKeys(fn ($r) => [$r->day => ['amount' => (float) $r->amount, 'count' => (float) $r->qty]])->all();

        return $this->minus($daily, 'purchase_returns', $from);
    }

    /** Resta las devoluciones del día en que se hicieron (el número de ventas u órdenes no cambia). */
    protected function minus(array $daily, string $table, Carbon $from): array
    {
        $returns = DB::table($table)->where('company_id', $this->companyId)
            ->whereBetween('return_date', [$from, $this->today->copy()->endOfDay()])
            ->groupByRaw('DATE(return_date)')
            ->selectRaw('DATE(return_date) as day, SUM(total) as amount')->pluck('amount', 'day');

        foreach ($returns as $day => $amount) {
            $daily[$day]['amount'] = ($daily[$day]['amount'] ?? 0) - (float) $amount;
            $daily[$day]['count'] ??= 0;
        }

        return $daily;
    }

    protected function saleLines(Carbon $from)
    {
        return DB::table('sale_details as d')->join('sales as s', 's.id', '=', 'd.sale_id')
            ->where('s.company_id', $this->companyId)->where('s.status', 'completada')
            ->whereNull('s.deleted_at')->whereNull('d.deleted_at')
            ->whereBetween('s.sale_date', [$from, $this->today->copy()->endOfDay()]);
    }

    // ---- Armado ----

    protected function sum(array $daily, Carbon $from, Carbon $to): array
    {
        $amount = 0.0;
        $count = 0.0;
        for ($day = $from->copy(); $day->lte($to); $day->addDay()) {
            $row = $daily[$day->toDateString()] ?? null;
            $amount += $row['amount'] ?? 0;
            $count += $row['count'] ?? 0;
        }

        return ['amount' => round($amount, 2), 'count' => round($count, 2)];
    }

    protected function comparison(array $daily, Carbon $from, Carbon $to, Carbon $prevFrom, Carbon $prevTo): array
    {
        $now = $this->sum($daily, $from, $to);
        $before = $this->sum($daily, $prevFrom, $prevTo);

        return $now + ['prev_amount' => $before['amount'], 'prev_count' => $before['count']];
    }

    protected function kpis(array $daily): array
    {
        return [
            'week'  => $this->comparison($daily, $this->weekStart, $this->today, $this->prevWeekStart, $this->prevWeekStart->copy()->addDays($this->weekStart->diffInDays($this->today))),
            'month' => $this->comparison($daily, $this->monthStart, $this->today, $this->prevMonthStart, $this->prevMonthTo),
        ];
    }

    /** Semana anterior completa contra la actual, día por día. */
    protected function byDay(array $daily): array
    {
        $rows = [];
        foreach (self::WEEKDAYS as $i => $label) {
            $now = $daily[$this->weekStart->copy()->addDays($i)->toDateString()] ?? [];
            $before = $daily[$this->prevWeekStart->copy()->addDays($i)->toDateString()] ?? [];
            $rows[] = [
                'label'       => $label,
                'amount'      => round($now['amount'] ?? 0, 2),
                'count'       => round($now['count'] ?? 0, 2),
                'prev_amount' => round($before['amount'] ?? 0, 2),
                'prev_count'  => round($before['count'] ?? 0, 2),
            ];
        }

        return $rows;
    }

    protected function weeks(array $daily): array
    {
        $rows = [];
        for ($i = 7; $i >= 0; $i--) {
            $start = $this->weekStart->copy()->subWeeks($i);
            $rows[] = ['label' => $start->format('d/m')] + $this->sum($daily, $start, $start->copy()->addDays(6));
        }

        return $rows;
    }

    protected function months(array $daily): array
    {
        $rows = [];
        for ($i = 5; $i >= 0; $i--) {
            $start = $this->monthStart->copy()->subMonthsNoOverflow($i);
            $rows[] = ['label' => self::MONTHS[$start->month - 1]] + $this->sum($daily, $start, $start->copy()->endOfMonth()->startOfDay());
        }

        return $rows;
    }

    /** Horas con más ventas del mes. sale_date no guarda la hora: se toma de cuándo se registró la venta. */
    protected function hours(): array
    {
        return DB::table('sales')->where('company_id', $this->companyId)->where('status', 'completada')->whereNull('deleted_at')
            ->whereBetween('sale_date', [$this->monthStart, $this->today->copy()->endOfDay()])
            ->groupByRaw('HOUR(created_at)')
            ->selectRaw('HOUR(created_at) as h, SUM(total) as amount, COUNT(*) as qty')
            ->get()->map(fn ($r) => [
                'label'  => sprintf('%02d:00', $r->h),
                'amount' => round((float) $r->amount, 2),
                'count'  => (int) $r->qty,
            ])->sortByDesc('amount')->take(8)->values()->all();
    }

    protected function topLines(Carbon $from, string $column, string $table, string $nameColumn): array
    {
        return $this->saleLines($from)->join("$table as t", 't.id', '=', "d.$column")
            ->groupBy('t.id', "t.$nameColumn")
            ->selectRaw("t.$nameColumn as name")
            ->selectRaw('SUM(CASE WHEN d.quantity > 0 THEN d.total * (d.quantity - d.returned_quantity) / d.quantity ELSE 0 END) as amount')
            ->selectRaw('SUM(d.quantity - d.returned_quantity) as qty')
            ->orderByDesc('amount')->limit(10)->get()->map(fn ($r) => $this->topRow($r))->all();
    }

    protected function topRow(object $r): array
    {
        return ['name' => $r->name, 'amount' => round((float) $r->amount, 2), 'count' => round((float) $r->qty, 2)];
    }
}
