<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\Warehouse;
use App\Services\BranchResolver;
use App\Services\StockService;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class StockController extends Controller
{
    public function __construct(protected StockService $stock)
    {
    }

    /** Existencias: total de la empresa y cuánto hay en cada almacén. */
    public function index(Request $request)
    {
        $companyId = $this->companyId();
        if (!$companyId) {
            return view('inventory.stock.index', ['products' => null, 'noCompany' => true, 'low' => false]);
        }

        $warehouses = $this->warehouses($companyId);
        $warehouse = $warehouses->firstWhere('id', $request->integer('warehouse'));
        $low = $request->boolean('low');
        $query = Product::with(['category', 'brand'])->where('company_id', $companyId)->orderBy('name');

        if ($low) {
            $query->whereColumn('current_stock', '<=', 'min_stock')->where('min_stock', '>', 0);
        }

        $products = $query->paginate(20)->withQueryString();
        // Existencias por almacén de los productos de la página: [producto][almacén] = cantidad.
        $byWarehouse = ProductStock::whereIn('product_id', $products->pluck('id'))->get()
            ->groupBy('product_id')->map(fn ($rows) => $rows->pluck('quantity', 'warehouse_id')->map(fn ($q) => (float) $q));

        return view('inventory.stock.index', [
            'products'    => $products,
            'noCompany'   => false,
            'low'         => $low,
            'warehouses'  => $warehouses,
            'warehouse'   => $warehouse,
            'byWarehouse' => $byWarehouse,
        ]);
    }

    /** Kardex: movimientos con su almacén, filtrables por almacén. */
    public function movements(Request $request)
    {
        $companyId = $this->companyId();
        if (!$companyId) {
            return view('inventory.stock.movements', ['movements' => null, 'noCompany' => true]);
        }

        $warehouses = $this->warehouses($companyId);
        $warehouseId = $request->integer('warehouse') ?: null;

        $movements = InventoryMovement::with(['product', 'warehouse'])
            ->where('company_id', $companyId)
            ->when($warehouseId, fn ($q) => $q->where('warehouse_id', $warehouseId))
            ->latest('movement_date')->latest('id')
            ->paginate(20)->withQueryString();

        return view('inventory.stock.movements', [
            'movements'   => $movements,
            'noCompany'   => false,
            'warehouses'  => $warehouses,
            'warehouseId' => $warehouseId,
        ]);
    }

    public function createMovement(Request $request)
    {
        $companyId = $this->companyId();
        if (!$companyId) {
            return redirect()->route('stock.index');
        }

        $products = Product::where('company_id', $companyId)->where('active', true)->orderBy('name')->get();

        return view('inventory.stock.movement', [
            'products'         => $products,
            'types'            => InventoryMovement::TYPES,
            'selectedProduct'  => $request->input('product_id'),
            'warehouses'       => $this->warehouses($companyId),
            // Almacén con el que arranca: el de la sucursal del usuario (o el principal).
            'defaultWarehouse' => $request->integer('warehouse')
                ?: $this->stock->warehouseFor($companyId, app(BranchResolver::class)->defaultFor($companyId, auth()->id())),
            'stocks'           => ProductStock::whereIn('product_id', $products->pluck('id'))->get()
                ->groupBy('product_id')->map(fn ($rows) => $rows->pluck('quantity', 'warehouse_id')->map(fn ($q) => (float) $q)),
        ]);
    }

    public function storeMovement(Request $request)
    {
        $companyId = $this->companyId();
        if (!$companyId) {
            return back()->withErrors(['error' => 'Debes operar dentro de una empresa.']);
        }

        $validated = $request->validate([
            'product_id'   => ['required', Rule::exists('products', 'id')->where('company_id', $companyId)],
            'warehouse_id' => ['required', Rule::exists('warehouses', 'id')->where('company_id', $companyId)->whereNull('deleted_at')],
            'type'         => ['required', 'in:' . implode(',', array_keys(InventoryMovement::TYPES))],
            'quantity'     => ['required', 'numeric', 'min:0'],
            'unit_cost'    => ['nullable', 'numeric', 'min:0'],
            'notes'        => ['nullable', 'string', 'max:255'],
        ], ['warehouse_id.required' => 'Elige el almacén.']);

        $qty = (float) $validated['quantity'];
        $note = $validated['notes'] ?? null;

        try {
            DB::transaction(function () use ($validated, $qty, $companyId, $note) {
                if ($validated['type'] === 'ajuste') {
                    // Conteo físico: la existencia del almacén queda en ese valor.
                    $this->stock->setTo((int) $validated['product_id'], $qty, $companyId, (int) $validated['warehouse_id'], $note, $validated['unit_cost'] ?? null);

                    return;
                }
                $delta = $validated['type'] === 'salida' ? -$qty : $qty;
                $this->stock->move((int) $validated['product_id'], $delta, $validated['type'], $companyId, 'MANUAL', $note ?? 'Movimiento manual', warehouseId: (int) $validated['warehouse_id']);
            });
        } catch (DomainException $e) {
            return back()->withInput()->withErrors(['quantity' => $e->getMessage()]);
        }

        return redirect()->route('stock.index', ['warehouse' => $validated['warehouse_id']])->with('success', 'Movimiento registrado. Stock actualizado.');
    }

    protected function warehouses(int $companyId)
    {
        return Warehouse::with('branches:id,warehouse_id,name')->where('company_id', $companyId)->where('active', true)->orderBy('id')->get();
    }

    protected function companyId(): ?int
    {
        return auth()->user()->getCurrentCompany()?->id;
    }
}
