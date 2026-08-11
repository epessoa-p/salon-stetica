<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\InventoryMovement;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StockController extends Controller
{
    /** Existencias: productos con su stock actual. */
    public function index(Request $request)
    {
        $companyId = $this->companyId();
        if (!$companyId) {
            return view('inventory.stock.index', ['products' => null, 'noCompany' => true, 'low' => false]);
        }

        $low = $request->boolean('low');
        $query = Product::with(['category', 'brand'])->where('company_id', $companyId)->orderBy('name');

        if ($low) {
            $query->whereColumn('current_stock', '<=', 'min_stock')->where('min_stock', '>', 0);
        }

        return view('inventory.stock.index', [
            'products' => $query->paginate(20)->withQueryString(),
            'noCompany' => false,
            'low' => $low,
        ]);
    }

    /** Ledger de movimientos. */
    public function movements()
    {
        $companyId = $this->companyId();
        if (!$companyId) {
            return view('inventory.stock.movements', ['movements' => null, 'noCompany' => true]);
        }

        $movements = InventoryMovement::with('product')
            ->where('company_id', $companyId)
            ->latest('movement_date')
            ->paginate(20);

        return view('inventory.stock.movements', ['movements' => $movements, 'noCompany' => false]);
    }

    public function createMovement(Request $request)
    {
        $companyId = $this->companyId();
        if (!$companyId) {
            return redirect()->route('stock.index');
        }

        return view('inventory.stock.movement', [
            'products' => Product::where('company_id', $companyId)->where('active', true)->orderBy('name')->get(),
            'types' => InventoryMovement::TYPES,
            'selectedProduct' => $request->input('product_id'),
        ]);
    }

    public function storeMovement(Request $request)
    {
        $companyId = $this->companyId();
        if (!$companyId) {
            return back()->withErrors(['error' => 'Debes operar dentro de una empresa.']);
        }

        $validated = $request->validate([
            'product_id' => ['required', 'exists:products,id'],
            'type'       => ['required', 'in:' . implode(',', array_keys(InventoryMovement::TYPES))],
            'quantity'   => ['required', 'numeric', 'min:0'],
            'unit_cost'  => ['nullable', 'numeric', 'min:0'],
            'notes'      => ['nullable', 'string', 'max:255'],
        ]);

        $product = Product::where('id', $validated['product_id'])->where('company_id', $companyId)->firstOrFail();
        $qty = (float) $validated['quantity'];

        if ($validated['type'] === 'salida' && $qty > (float) $product->current_stock) {
            return back()->withInput()->withErrors(['quantity' => 'No hay stock suficiente. Actual: ' . $product->current_stock]);
        }

        try {
            DB::transaction(function () use ($product, $validated, $qty, $companyId) {
                // Ajusta el stock según el tipo.
                $newStock = match ($validated['type']) {
                    'entrada' => (float) $product->current_stock + $qty,
                    'salida'  => (float) $product->current_stock - $qty,
                    'ajuste'  => $qty, // valor absoluto
                };

                $product->update(['current_stock' => max(0, $newStock)]);

                InventoryMovement::create([
                    'company_id'    => $companyId,
                    'warehouse_id'  => null,
                    'branch_id'     => null,
                    'product_id'    => $product->id,
                    'user_id'       => auth()->id(),
                    'type'          => $validated['type'],
                    'quantity'      => $qty,
                    'unit_cost'     => $validated['unit_cost'] ?? null,
                    'notes'         => $validated['notes'] ?? null,
                    'movement_date' => now(),
                ]);
            });
        } catch (\Throwable $e) {
            return back()->withInput()->withErrors(['error' => 'No fue posible registrar el movimiento.']);
        }

        return redirect()->route('stock.index')->with('success', 'Movimiento registrado. Stock actualizado.');
    }

    protected function companyId(): ?int
    {
        return auth()->user()->getCurrentCompany()?->id;
    }
}
