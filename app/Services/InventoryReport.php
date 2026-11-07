<?php

namespace App\Services;

use App\Models\InventoryMovement;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Reporte de inventario: cuánto hay hoy, cuánto vale y qué requiere atención.
 * Es una foto del momento, no depende de un período. Lo usan la web y la API.
 *
 * Reglas:
 *  - Solo productos activos. El valor usa el costo y el precio actuales.
 *  - Con un almacén elegido, las cantidades son las de ese almacén. «Para reponer»
 *    siempre mira el total, porque el mínimo se define por producto, no por almacén.
 *  - «Sin movimiento» mira la última salida real: las transferencias entre almacenes
 *    y los ajustes no cuentan como venta o consumo, y un producto recién creado
 *    no se considera detenido hasta que pasen los días.
 */
class InventoryReport
{
    public const IDLE_DAYS = 90;
    public const LIST_LIMIT = 30;

    public function __construct(protected int $companyId)
    {
    }

    public function build(?int $warehouseId = null): array
    {
        $products = $this->products();
        $inPlace = $warehouseId ? $this->quantitiesIn($warehouseId) : null;
        $qty = fn ($p) => $inPlace === null ? (float) $p->current_stock : (float) ($inPlace[$p->id] ?? 0);

        // Con almacén elegido, solo cuenta lo que está en él.
        $here = $inPlace === null ? $products : $products->filter(fn ($p) => $qty($p) > 0);

        $cost = $here->sum(fn ($p) => $qty($p) * (float) $p->cost);
        $sale = $here->sum(fn ($p) => $qty($p) * (float) $p->price);

        return [
            'warehouse_id' => $warehouseId,
            'warehouses'   => DB::table('warehouses')->where('company_id', $this->companyId)->whereNull('deleted_at')->where('active', true)
                ->orderBy('name')->get(['id', 'name'])->map(fn ($w) => ['id' => $w->id, 'name' => $w->name])->values()->all(),
            'summary' => [
                'products' => $here->count(),
                'units'    => round($here->sum($qty), 2),
                'cost'     => round($cost, 2),
                'sale'     => round($sale, 2),
                'profit'   => round($sale - $cost, 2),
                'margin'   => $sale > 0 ? round(($sale - $cost) / $sale * 100, 1) : 0,
            ],
            'categories' => $this->categories($here, $qty),
            'restock'    => $this->restock($products),
            'idle'       => $this->idle($here, $qty),
            'below_cost' => $this->belowCost($here, $qty),
            'no_cost'    => $here->filter(fn ($p) => $qty($p) > 0 && (float) $p->cost <= 0)->count(),
        ];
    }

    /** Movimientos del período por tipo. */
    public function movements(Carbon $from, Carbon $to): array
    {
        return DB::table('inventory_movements')->where('company_id', $this->companyId)->whereNull('deleted_at')
            ->whereBetween('movement_date', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->selectRaw('type, COUNT(*) as n, SUM(quantity) as qty')->groupBy('type')->orderBy('type')->get()
            ->map(fn ($r) => ['type' => InventoryMovement::TYPES[$r->type] ?? $r->type, 'count' => (int) $r->n, 'quantity' => round((float) $r->qty, 2)])
            ->all();
    }

    protected function products(): Collection
    {
        return DB::table('products as p')
            ->leftJoin('product_categories as pc', 'pc.id', '=', 'p.product_category_id')
            ->where('p.company_id', $this->companyId)->where('p.active', true)->whereNull('p.deleted_at')
            ->orderBy('p.name')
            ->get(['p.id', 'p.name', 'p.cost', 'p.price', 'p.min_stock', 'p.current_stock', 'p.created_at', 'pc.name as category_name']);
    }

    /** Un producto sin filas por almacén todavía tiene todo su total en el principal. */
    protected function quantitiesIn(int $warehouseId): array
    {
        $quantities = DB::table('product_stocks')->where('company_id', $this->companyId)->where('warehouse_id', $warehouseId)
            ->pluck('quantity', 'product_id')->map(fn ($q) => (float) $q)->all();

        if ($warehouseId === app(StockService::class)->defaultWarehouse($this->companyId)) {
            $unplaced = DB::table('products as p')->where('p.company_id', $this->companyId)
                ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('product_stocks as s')->whereColumn('s.product_id', 'p.id'))
                ->pluck('p.current_stock', 'p.id');
            foreach ($unplaced as $id => $quantity) {
                $quantities[$id] = (float) $quantity;
            }
        }

        return $quantities;
    }

    protected function categories(Collection $products, callable $qty): array
    {
        $rows = $products
            ->groupBy(fn ($p) => $p->category_name ?: 'Sin categoría')
            ->map(fn ($group, $name) => [
                'name'     => $name,
                'products' => $group->count(),
                'units'    => round($group->sum($qty), 2),
                'cost'     => round($group->sum(fn ($p) => $qty($p) * (float) $p->cost), 2),
                'sale'     => round($group->sum(fn ($p) => $qty($p) * (float) $p->price), 2),
            ])
            ->sortByDesc('sale')->values();

        $top = (float) $rows->max('sale');

        return $rows->map(fn ($r) => $r + ['share' => $top > 0 ? round($r['sale'] / $top, 4) : 0])->all();
    }

    protected function restock(Collection $products): array
    {
        $rows = $products
            ->filter(fn ($p) => (float) $p->current_stock <= 0 || ((float) $p->min_stock > 0 && (float) $p->current_stock <= (float) $p->min_stock))
            ->map(function ($p) {
                $stock = (float) $p->current_stock;
                $missing = max((float) $p->min_stock - $stock, 0);

                return [
                    'name'    => $p->name,
                    'status'  => $stock <= 0 ? 'agotado' : 'bajo',
                    'stock'   => round($stock, 2),
                    'min'     => round((float) $p->min_stock, 2),
                    'missing' => round($missing, 2),
                    'cost'    => round($missing * (float) $p->cost, 2),
                ];
            });

        return [
            'out'   => $rows->where('status', 'agotado')->count(),
            'low'   => $rows->where('status', 'bajo')->count(),
            'cost'  => round($rows->sum('cost'), 2),
            'count' => $rows->count(),
            'items' => $rows->sortBy([['status', 'asc'], ['cost', 'desc']])->take(self::LIST_LIMIT)->values()->all(),
        ];
    }

    protected function idle(Collection $products, callable $qty): array
    {
        $lastExit = DB::table('inventory_movements as m')->where('m.company_id', $this->companyId)->whereNull('m.deleted_at')->where('m.type', 'salida')
            ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('stock_transfers as t')->where('t.company_id', $this->companyId)
                ->whereRaw('CONVERT(t.transfer_number USING utf8mb4) COLLATE utf8mb4_unicode_ci = CONVERT(m.reference USING utf8mb4) COLLATE utf8mb4_unicode_ci'))
            ->groupBy('m.product_id')->selectRaw('m.product_id, MAX(m.movement_date) as last_at')->pluck('last_at', 'm.product_id');

        $cutoff = today()->subDays(self::IDLE_DAYS);

        $rows = $products->filter(fn ($p) => $qty($p) > 0)
            ->map(function ($p) use ($lastExit, $cutoff, $qty) {
                $last = isset($lastExit[$p->id]) ? Carbon::parse($lastExit[$p->id]) : null;
                $since = $last ?? ($p->created_at ? Carbon::parse($p->created_at) : null);
                if (!$since || $since->gte($cutoff)) {
                    return null;
                }

                return [
                    'name'      => $p->name,
                    'units'     => round($qty($p), 2),
                    'value'     => round($qty($p) * (float) $p->cost, 2),
                    'last_exit' => $last?->toDateString(),
                    'days'      => (int) $since->copy()->startOfDay()->diffInDays(today()),
                ];
            })
            ->filter();

        return [
            'days'  => self::IDLE_DAYS,
            'value' => round($rows->sum('value'), 2),
            'count' => $rows->count(),
            'items' => $rows->sortByDesc('value')->take(self::LIST_LIMIT)->values()->all(),
        ];
    }

    /** Se vende perdiendo o sin ganar. Sin costo cargado no se puede saber: se avisa aparte. */
    protected function belowCost(Collection $products, callable $qty): array
    {
        $rows = $products->filter(fn ($p) => (float) $p->cost > 0 && (float) $p->price <= (float) $p->cost)
            ->map(fn ($p) => [
                'name'  => $p->name,
                'cost'  => round((float) $p->cost, 2),
                'price' => round((float) $p->price, 2),
                'units' => round($qty($p), 2),
            ]);

        return [
            'count' => $rows->count(),
            'items' => $rows->sortByDesc(fn ($r) => $r['cost'] - $r['price'])->take(self::LIST_LIMIT)->values()->all(),
        ];
    }
}
