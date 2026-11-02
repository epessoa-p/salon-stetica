<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\CashMovement;
use App\Models\CashSession;
use App\Models\Client;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Sale;
use App\Models\SaleItem;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Modulo de Reportes: una sola pantalla con los tipos como pestanas.
 * Es administrativo (no depende del plan), pero cada pestana solo se ofrece
 * si la empresa tiene el modulo de origen y el usuario el permiso.
 */
class ReportController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();
        $company = $user->getCurrentCompany();

        if (!$company) {
            return view('reports.index', ['noCompany' => true, 'available' => [], 'type' => null]);
        }

        $available = $this->availableFor($user, $company);

        $type = $request->input('type');
        if (!$type || !isset($available[$type])) {
            $type = array_key_first($available);
        }

        $from = Carbon::parse($request->input('from', today()->startOfMonth()->toDateString()))->startOfDay();
        $to   = Carbon::parse($request->input('to', today()->toDateString()))->endOfDay();

        $data = [
            'noCompany' => false,
            'available' => $available,
            'type' => $type,
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
        ];

        if ($type) {
            $data['report'] = $this->build($type, $from, $to, $company->id);
        }

        return view('reports.index', $data);
    }

    /** Reportes que puede ver el usuario: módulo en el plan + permiso. Lo usa también la app. */
    public function availableFor(\App\Models\User $user, \App\Models\Company $company): array
    {
        $can = fn (string $feature, string $perm) => $company->planAllows($feature)
            && ($user->is_super_admin || $user->hasPermissionInCompany($perm, $company));

        return array_filter([
            'ventas'     => $can('sales', 'sales.view') ? 'Ventas' : null,
            'servicios'  => $can('catalog', 'services.view') ? 'Servicios' : null,
            'clientes'   => $can('crm', 'clients.view') ? 'Clientes' : null,
            'inventario' => $can('inventory', 'stock.view') ? 'Inventario' : null,
            'compras'    => $can('purchasing', 'purchases.view') ? 'Compras' : null,
            'caja'       => $can('finance', 'cash.view') ? 'Caja' : null,
            'analisis'   => $can('sales', 'sales.view') ? 'Análisis' : null,
            'ganancias'  => $can('sales', 'sales.view') && $can('finance', 'cash.view') ? 'Ganancias' : null,
        ]);
    }

    /** Indicadores y tablas de un reporte (misma estructura en la web y la app). */
    public function build(string $type, Carbon $from, Carbon $to, int $companyId): array
    {
        return match ($type) {
            'ventas'     => $this->ventas($from, $to),
            'servicios'  => $this->servicios($from, $to),
            'clientes'   => $this->clientes($from, $to),
            'inventario' => $this->inventario($from, $to),
            'compras'    => $this->compras($from, $to),
            'caja'       => $this->caja($from, $to, $companyId),
            'analisis'   => $this->analisis($from, $to),
            'ganancias'  => $this->ganancias($from, $to),
            default      => [],
        };
    }

    /**
     * Análisis: el período contra el anterior de igual duración, días y horas
     * con más ventas, y lo que vendió cada profesional.
     */
    protected function analisis(Carbon $from, Carbon $to): array
    {
        $days = $from->diffInDays($to) + 1;
        $prevFrom = $from->copy()->subDays($days);
        $prevTo = $from->copy()->subSecond();

        $stats = function (Carbon $a, Carbon $b) {
            $sales = Sale::where('status', 'completada')->whereBetween('sale_date', [$a, $b]);
            $total = (float) (clone $sales)->sum('total') - (float) \App\Models\SaleReturn::whereBetween('return_date', [$a, $b])->sum('total');
            $count = (clone $sales)->count();
            $appts = Appointment::whereBetween('starts_at', [$a, $b]);
            $apptCount = (clone $appts)->count();
            $lost = (clone $appts)->whereIn('status', ['cancelada', 'no_show'])->count();

            return [
                'total'   => $total,
                'count'   => $count,
                'ticket'  => $count ? $total / $count : 0,
                'clients' => (clone $sales)->whereNotNull('client_id')->distinct()->count('client_id'),
                'new'     => Client::whereBetween('created_at', [$a, $b])->count(),
                'appts'   => $apptCount,
                'lost'    => $apptCount ? $lost * 100 / $apptCount : 0,
            ];
        };
        $now = $stats($from, $to);
        $prev = $stats($prevFrom, $prevTo);
        $delta = fn (float $a, float $b) => $b == 0.0 ? ($a > 0 ? 'nuevo' : '—') : (($a >= $b ? '+' : '') . number_format(($a - $b) * 100 / $b, 1) . ' %');
        $money = fn ($n) => number_format((float) $n, 2);

        $weekdays = [1 => 'Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
        $base = fn () => Sale::where('status', 'completada')->whereBetween('sale_date', [$from, $to]);

        return [
            'kpis' => [
                ['label' => 'Ventas netas vs anterior', 'value' => $delta($now['total'], $prev['total']), 'icon' => 'bi-graph-up-arrow', 'color' => $now['total'] >= $prev['total'] ? '#10b981' : '#ef4444'],
                ['label' => 'Ticket promedio', 'value' => $money($now['ticket']), 'icon' => 'bi-receipt', 'color' => '#6366f1'],
                ['label' => 'Clientes atendidos', 'value' => $now['clients'], 'icon' => 'bi-people', 'color' => '#ec4899'],
                ['label' => 'Citas perdidas', 'value' => number_format($now['lost'], 1) . ' %', 'icon' => 'bi-calendar-x', 'color' => $now['lost'] > 15 ? '#ef4444' : '#f59e0b'],
            ],
            'tables' => [
                [
                    'title' => 'Comparación con el período anterior (' . $prevFrom->format('d/m') . '–' . $prevTo->format('d/m') . ')',
                    'head' => ['Indicador', 'Ahora', 'Antes', 'Variación'],
                    'rows' => [
                        ['Ventas netas', $money($now['total']), $money($prev['total']), $delta($now['total'], $prev['total'])],
                        ['N. de ventas', $now['count'], $prev['count'], $delta($now['count'], $prev['count'])],
                        ['Ticket promedio', $money($now['ticket']), $money($prev['ticket']), $delta($now['ticket'], $prev['ticket'])],
                        ['Clientes atendidos', $now['clients'], $prev['clients'], $delta($now['clients'], $prev['clients'])],
                        ['Clientes nuevos', $now['new'], $prev['new'], $delta($now['new'], $prev['new'])],
                        ['Citas', $now['appts'], $prev['appts'], $delta($now['appts'], $prev['appts'])],
                        ['Canceladas o no asistió', number_format($now['lost'], 1) . ' %', number_format($prev['lost'], 1) . ' %', ''],
                    ],
                ],
                [
                    'title' => 'Ventas por día de la semana',
                    'head' => ['Día', 'Ventas', 'Total'],
                    'rows' => $base()->selectRaw('DAYOFWEEK(sale_date) as d, COUNT(*) as n, SUM(total) as monto')
                        ->groupBy('d')->orderByDesc('monto')->get()
                        ->map(fn ($r) => [$weekdays[(int) $r->d] ?? $r->d, $r->n, $money($r->monto)])->all(),
                ],
                [
                    'title' => 'Horas con más ventas',
                    'head' => ['Hora', 'Ventas', 'Total'],
                    'rows' => $base()->selectRaw('HOUR(sale_date) as h, COUNT(*) as n, SUM(total) as monto')
                        ->groupBy('h')->orderByDesc('n')->limit(8)->get()
                        ->map(fn ($r) => [sprintf('%02d:00 – %02d:59', $r->h, $r->h), $r->n, $money($r->monto)])->all(),
                ],
                [
                    'title' => 'Ventas por profesional',
                    'head' => ['Profesional', 'Servicios', 'Total'],
                    'rows' => SaleItem::selectRaw('personal_id, SUM(CASE WHEN service_id IS NOT NULL THEN quantity ELSE 0 END) as servicios, SUM(total) as monto')
                        ->whereNotNull('personal_id')
                        ->whereHas('sale', fn ($q) => $q->where('status', 'completada')->whereBetween('sale_date', [$from, $to]))
                        ->groupBy('personal_id')->orderByDesc('monto')->with('specialist')->limit(15)->get()
                        ->map(fn ($r) => [$r->specialist?->full_name ?? 'N/D', rtrim(rtrim(number_format($r->servicios, 2), '0'), '.'), $money($r->monto)])->all(),
                ],
            ],
        ];
    }

    /**
     * Ganancias (estado de resultados simple): ventas netas − costo de los
     * productos vendidos − comisiones − gastos operativos.
     * Gastos operativos = egresos de caja sin propinas (son del profesional),
     * pagos de comisiones (ya cuentan al generarse), pagos a proveedores (el
     * costo entra como costo de lo vendido) ni devoluciones/anulaciones (ya
     * restan de las ventas).
     */
    protected function ganancias(Carbon $from, Carbon $to): array
    {
        $money = fn ($n) => number_format((float) $n, 2);
        $sales = (float) Sale::where('status', 'completada')->whereBetween('sale_date', [$from, $to])->sum('total');
        $returns = (float) \App\Models\SaleReturn::whereBetween('return_date', [$from, $to])->sum('total');
        $net = $sales - $returns;

        // Costo de lo vendido: productos (sueltos o en combos), neto de devoluciones, al costo actual.
        $productLines = SaleItem::selectRaw('product_id, SUM(quantity - returned_quantity) as cant, SUM(CASE WHEN quantity > 0 THEN total * (quantity - returned_quantity) / quantity ELSE 0 END) as ingreso')
            ->whereNotNull('product_id')
            ->whereHas('sale', fn ($q) => $q->where('status', 'completada')->whereBetween('sale_date', [$from, $to]))
            ->groupBy('product_id')->with('product')->get();
        $cogs = (float) $productLines->sum(fn ($l) => (float) $l->cant * (float) ($l->product?->cost ?? 0));

        $commissions = (float) \App\Models\Commission::whereBetween('accrued_at', [$from, $to])->sum('amount');

        $excluded = ['Propinas', 'Comisiones', 'Pago a proveedores', 'Devoluciones'];
        $expenses = CashMovement::where('type', 'egreso')->whereBetween('movement_date', [$from->toDateString(), $to->toDateString()])
            ->where(fn ($q) => $q->whereNull('category')->orWhereNotIn('category', $excluded))
            ->where('concept', 'not like', 'Anulación%');
        $expenseTotal = (float) (clone $expenses)->sum('amount');

        $gross = $net - $cogs;
        $profit = $gross - $commissions - $expenseTotal;
        $margin = $net > 0 ? $profit * 100 / $net : 0;

        return [
            'kpis' => [
                ['label' => 'Ventas netas', 'value' => $money($net), 'icon' => 'bi-cash-coin', 'color' => '#14b8a6'],
                ['label' => 'Utilidad bruta', 'value' => $money($gross), 'icon' => 'bi-bar-chart', 'color' => '#6366f1'],
                ['label' => 'Ganancia neta', 'value' => $money($profit), 'icon' => 'bi-piggy-bank', 'color' => $profit >= 0 ? '#10b981' : '#ef4444'],
                ['label' => 'Margen neto', 'value' => number_format($margin, 1) . ' %', 'icon' => 'bi-percent', 'color' => $margin >= 0 ? '#0ea5e9' : '#ef4444'],
            ],
            'tables' => [
                [
                    'title' => 'Estado de resultados',
                    'head' => ['Concepto', 'Monto'],
                    'rows' => [
                        ['Ventas', $money($sales)],
                        ['(−) Devoluciones', $money(-$returns)],
                        ['Ventas netas', $money($net)],
                        ['(−) Costo de productos vendidos', $money(-$cogs)],
                        ['Utilidad bruta', $money($gross)],
                        ['(−) Comisiones del personal', $money(-$commissions)],
                        ['(−) Gastos operativos', $money(-$expenseTotal)],
                        ['Ganancia neta', $money($profit)],
                    ],
                ],
                [
                    'title' => 'Gastos operativos por categoría',
                    'head' => ['Categoría', 'Total'],
                    'rows' => (clone $expenses)->selectRaw('category, SUM(amount) as monto')->groupBy('category')->orderByDesc('monto')->get()
                        ->map(fn ($r) => [$r->category ?: 'Sin categoría', $money($r->monto)])->all(),
                ],
                [
                    'title' => 'Margen por producto',
                    'head' => ['Producto', 'Vendidos', 'Ingreso', 'Costo', 'Margen'],
                    'rows' => $productLines->sortByDesc(fn ($l) => (float) $l->ingreso - (float) $l->cant * (float) ($l->product?->cost ?? 0))->take(15)
                        ->map(fn ($l) => [
                            $l->product?->name ?? 'N/D',
                            rtrim(rtrim(number_format($l->cant, 2), '0'), '.'),
                            $money($l->ingreso),
                            $money((float) $l->cant * (float) ($l->product?->cost ?? 0)),
                            $money((float) $l->ingreso - (float) $l->cant * (float) ($l->product?->cost ?? 0)),
                        ])->values()->all(),
                ],
            ],
            'note' => 'El costo de productos usa el costo actual de cada producto. Los gastos operativos no incluyen propinas, pagos de comisiones, pagos a proveedores ni devoluciones.',
        ];
    }

    protected function ventas(Carbon $from, Carbon $to): array
    {
        $base = fn () => Sale::where('status', 'completada')->whereBetween('sale_date', [$from, $to]);

        $total = (float) $base()->sum('total');
        $count = $base()->count();
        // Las devoluciones restan en la fecha en que se hicieron (no en la de la venta).
        $returned = (float) \App\Models\SaleReturn::whereBetween('return_date', [$from, $to])->sum('total');

        return [
            'kpis' => [
                ['label' => 'Total vendido', 'value' => number_format($total, 2), 'icon' => 'bi-cash-coin', 'color' => '#14b8a6'],
                ['label' => 'Devoluciones', 'value' => number_format($returned, 2), 'icon' => 'bi-arrow-return-left', 'color' => '#f59e0b'],
                ['label' => 'Neto', 'value' => number_format($total - $returned, 2), 'icon' => 'bi-cash-stack', 'color' => '#0ea5e9'],
                ['label' => 'N. de ventas', 'value' => $count, 'icon' => 'bi-receipt', 'color' => '#6366f1'],
                ['label' => 'Ticket promedio', 'value' => number_format($count ? $total / $count : 0, 2), 'icon' => 'bi-graph-up', 'color' => '#10b981'],
            ],
            'tables' => [
                [
                    'title' => 'Por metodo de pago',
                    'head' => ['Metodo', 'Ventas', 'Total'],
                    'rows' => $base()->selectRaw('payment_method, COUNT(*) as n, SUM(total) as monto')
                        ->groupBy('payment_method')->orderByDesc('monto')->get()
                        ->map(fn ($r) => [ucfirst($r->payment_method), $r->n, number_format($r->monto, 2)])->all(),
                ],
                ...(\App\Models\Branch::count() > 1 ? [[
                    'title' => 'Por sucursal',
                    'head' => ['Sucursal', 'Ventas', 'Total'],
                    'rows' => $base()->selectRaw('branch_id, COUNT(*) as n, SUM(total) as monto')
                        ->groupBy('branch_id')->orderByDesc('monto')->with('branch')->get()
                        ->map(fn ($r) => [$r->branch?->name ?? 'Sin sucursal', $r->n, number_format($r->monto, 2)])->all(),
                ]] : []),
                [
                    'title' => 'Por dia',
                    'head' => ['Dia', 'Ventas', 'Total'],
                    'rows' => $base()->selectRaw('DATE(sale_date) as dia, COUNT(*) as n, SUM(total) as monto')
                        ->groupBy('dia')->orderBy('dia')->get()
                        ->map(fn ($r) => [Carbon::parse($r->dia)->format('d/m/Y'), $r->n, number_format($r->monto, 2)])->all(),
                ],
            ],
        ];
    }

    protected function servicios(Carbon $from, Carbon $to): array
    {
        // Neto de devoluciones: se descuenta la parte devuelta de cada línea.
        $vendidos = SaleItem::selectRaw('service_id, SUM(quantity - returned_quantity) as cantidad,'
                . ' SUM(CASE WHEN quantity > 0 THEN total * (quantity - returned_quantity) / quantity ELSE 0 END) as monto')
            ->whereNotNull('service_id')
            ->whereHas('sale', fn ($q) => $q->where('status', 'completada')->whereBetween('sale_date', [$from, $to]))
            ->groupBy('service_id')->orderByDesc('monto')->with('service')->limit(15)->get();

        $citas = Appointment::selectRaw('service_id, COUNT(*) as n')
            ->whereNotNull('service_id')
            ->whereBetween('starts_at', [$from, $to])
            ->groupBy('service_id')->orderByDesc('n')->with('service')->limit(15)->get();

        return [
            'kpis' => [
                ['label' => 'Servicios vendidos', 'value' => (int) $vendidos->sum('cantidad'), 'icon' => 'bi-scissors', 'color' => '#a855f7'],
                ['label' => 'Ingresos por servicios', 'value' => number_format((float) $vendidos->sum('monto'), 2), 'icon' => 'bi-cash', 'color' => '#14b8a6'],
                ['label' => 'Citas en el periodo', 'value' => (int) $citas->sum('n'), 'icon' => 'bi-calendar-check', 'color' => '#6366f1'],
            ],
            'tables' => [
                [
                    'title' => 'Servicios mas vendidos',
                    'head' => ['Servicio', 'Cantidad', 'Total'],
                    'rows' => $vendidos->map(fn ($r) => [$r->service?->name ?? 'N/D', rtrim(rtrim(number_format($r->cantidad, 2), '0'), '.'), number_format($r->monto, 2)])->all(),
                ],
                [
                    'title' => 'Servicios mas agendados',
                    'head' => ['Servicio', 'Citas'],
                    'rows' => $citas->map(fn ($r) => [$r->service?->name ?? 'N/D', $r->n])->all(),
                ],
            ],
        ];
    }

    protected function clientes(Carbon $from, Carbon $to): array
    {
        $nuevos = Client::whereBetween('created_at', [$from, $to])->count();

        $top = Sale::selectRaw('client_id, COUNT(*) as n, SUM(total) as monto')
            ->where('status', 'completada')->whereNotNull('client_id')
            ->whereBetween('sale_date', [$from, $to])
            ->groupBy('client_id')->orderByDesc('monto')->with('client')->limit(15)->get();

        return [
            'kpis' => [
                ['label' => 'Clientes nuevos', 'value' => $nuevos, 'icon' => 'bi-person-plus', 'color' => '#ec4899'],
                ['label' => 'Clientes totales', 'value' => Client::count(), 'icon' => 'bi-people', 'color' => '#6366f1'],
                ['label' => 'Clientes que compraron', 'value' => $top->count(), 'icon' => 'bi-bag-check', 'color' => '#10b981'],
            ],
            'tables' => [
                [
                    'title' => 'Clientes que mas gastaron',
                    'head' => ['Cliente', 'Compras', 'Total'],
                    'rows' => $top->map(fn ($r) => [$r->client?->full_name ?? 'N/D', $r->n, number_format($r->monto, 2)])->all(),
                ],
            ],
        ];
    }

    protected function inventario(Carbon $from, Carbon $to): array
    {
        $productos = Product::where('active', true)->get();
        $valorCosto = $productos->sum(fn ($p) => (float) $p->current_stock * (float) $p->cost);
        $valorVenta = $productos->sum(fn ($p) => (float) $p->current_stock * (float) $p->price);
        $bajos = $productos->filter(fn ($p) => $p->isLowStock());

        return [
            'kpis' => [
                ['label' => 'Productos activos', 'value' => $productos->count(), 'icon' => 'bi-box', 'color' => '#f59e0b'],
                ['label' => 'Valor del stock (costo)', 'value' => number_format($valorCosto, 2), 'icon' => 'bi-cash-stack', 'color' => '#14b8a6'],
                ['label' => 'Stock bajo', 'value' => $bajos->count(), 'icon' => 'bi-exclamation-triangle', 'color' => $bajos->count() ? '#ef4444' : '#10b981'],
            ],
            'tables' => [
                [
                    'title' => 'Productos con stock bajo',
                    'head' => ['Producto', 'Stock', 'Minimo'],
                    'rows' => $bajos->take(15)->map(fn ($p) => [$p->name, rtrim(rtrim(number_format($p->current_stock, 2), '0'), '.'), rtrim(rtrim(number_format($p->min_stock, 2), '0'), '.')])->values()->all(),
                ],
                [
                    'title' => 'Movimientos del periodo',
                    'head' => ['Tipo', 'Movimientos', 'Cantidad total'],
                    'rows' => InventoryMovement::selectRaw('type, COUNT(*) as n, SUM(quantity) as cant')
                        ->whereBetween('movement_date', [$from, $to])
                        ->groupBy('type')->get()
                        ->map(fn ($r) => [InventoryMovement::TYPES[$r->type] ?? $r->type, $r->n, rtrim(rtrim(number_format($r->cant, 2), '0'), '.')])->all(),
                ],
                [
                    'title' => 'Valorizacion',
                    'head' => ['Concepto', 'Monto'],
                    'rows' => [
                        ['Valor a costo', number_format($valorCosto, 2)],
                        ['Valor a precio de venta', number_format($valorVenta, 2)],
                        ['Margen potencial', number_format($valorVenta - $valorCosto, 2)],
                    ],
                ],
            ],
        ];
    }

    protected function compras(Carbon $from, Carbon $to): array
    {
        $base = fn () => PurchaseOrder::where('status', 'recibida')->whereBetween('entry_date', [$from, $to]);

        $total = (float) $base()->sum('total');

        return [
            'kpis' => [
                ['label' => 'Total comprado', 'value' => number_format($total, 2), 'icon' => 'bi-truck', 'color' => '#f97316'],
                ['label' => 'Ordenes recibidas', 'value' => $base()->count(), 'icon' => 'bi-box-arrow-in-down', 'color' => '#6366f1'],
                ['label' => 'Ordenes pendientes', 'value' => PurchaseOrder::where('status', 'pendiente')->count(), 'icon' => 'bi-hourglass-split', 'color' => '#f59e0b'],
            ],
            'tables' => [
                [
                    'title' => 'Compras por proveedor',
                    'head' => ['Proveedor', 'Ordenes', 'Total'],
                    'rows' => $base()->selectRaw('supplier_id, COUNT(*) as n, SUM(total) as monto')
                        ->groupBy('supplier_id')->orderByDesc('monto')->with('provider')->get()
                        ->map(fn ($r) => [$r->provider?->name ?? 'N/D', $r->n, number_format($r->monto, 2)])->all(),
                ],
            ],
        ];
    }

    protected function caja(Carbon $from, Carbon $to, int $companyId): array
    {
        $ingresos = (float) CashMovement::where('type', 'ingreso')->whereBetween('movement_date', [$from, $to])->sum('amount');
        $egresos  = (float) CashMovement::where('type', 'egreso')->whereBetween('movement_date', [$from, $to])->sum('amount');

        $arqueos = CashSession::with('register')
            ->where('status', 'closed')
            ->whereBetween('closed_at', [$from, $to])
            ->whereHas('register', fn ($q) => $q->where('company_id', $companyId))
            ->orderByDesc('closed_at')->limit(15)->get();

        return [
            'kpis' => [
                ['label' => 'Ingresos', 'value' => number_format($ingresos, 2), 'icon' => 'bi-arrow-down-circle', 'color' => '#10b981'],
                ['label' => 'Gastos', 'value' => number_format($egresos, 2), 'icon' => 'bi-arrow-up-circle', 'color' => '#ef4444'],
                ['label' => 'Balance', 'value' => number_format($ingresos - $egresos, 2), 'icon' => 'bi-wallet2', 'color' => ($ingresos - $egresos) >= 0 ? '#14b8a6' : '#ef4444'],
            ],
            'tables' => [
                [
                    'title' => 'Por categoria',
                    'head' => ['Categoria', 'Tipo', 'Total'],
                    'rows' => CashMovement::selectRaw('category, type, SUM(amount) as monto')
                        ->whereBetween('movement_date', [$from, $to])
                        ->groupBy('category', 'type')->orderByDesc('monto')->get()
                        ->map(fn ($r) => [$r->category ?: 'Sin categoria', CashMovement::TYPES[$r->type] ?? $r->type, number_format($r->monto, 2)])->all(),
                ],
                [
                    'title' => 'Arqueos cerrados',
                    'head' => ['Caja', 'Cierre', 'Esperado', 'Contado', 'Diferencia'],
                    'rows' => $arqueos->map(fn ($s) => [
                        $s->register?->name ?? 'N/D',
                        optional($s->closed_at)->format('d/m/Y H:i'),
                        number_format($s->expected_amount, 2),
                        number_format($s->closing_amount, 2),
                        number_format($s->difference, 2),
                    ])->all(),
                ],
            ],
        ];
    }
}
