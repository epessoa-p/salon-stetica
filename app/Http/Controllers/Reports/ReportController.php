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

        $can = fn (string $feature, string $perm) => $company->planAllows($feature)
            && $user->hasPermissionInCompany($perm, $company);

        $available = array_filter([
            'ventas'     => $can('sales', 'sales.view') ? 'Ventas' : null,
            'servicios'  => $can('catalog', 'services.view') ? 'Servicios' : null,
            'clientes'   => $can('crm', 'clients.view') ? 'Clientes' : null,
            'inventario' => $can('inventory', 'stock.view') ? 'Inventario' : null,
            'compras'    => $can('purchasing', 'purchases.view') ? 'Compras' : null,
            'caja'       => $can('finance', 'cash.view') ? 'Caja' : null,
        ]);

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
            $data['report'] = match ($type) {
                'ventas'     => $this->ventas($from, $to),
                'servicios'  => $this->servicios($from, $to),
                'clientes'   => $this->clientes($from, $to),
                'inventario' => $this->inventario($from, $to),
                'compras'    => $this->compras($from, $to),
                'caja'       => $this->caja($from, $to, $company->id),
                default      => [],
            };
        }

        return view('reports.index', $data);
    }

    protected function ventas(Carbon $from, Carbon $to): array
    {
        $base = fn () => Sale::where('status', 'completada')->whereBetween('sale_date', [$from, $to]);

        $total = (float) $base()->sum('total');
        $count = $base()->count();

        return [
            'kpis' => [
                ['label' => 'Total vendido', 'value' => number_format($total, 2), 'icon' => 'bi-cash-coin', 'color' => '#14b8a6'],
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
        $vendidos = SaleItem::selectRaw('service_id, SUM(quantity) as cantidad, SUM(total) as monto')
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
