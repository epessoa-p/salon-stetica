<?php

namespace App\Http\Controllers\Purchasing;

use App\Http\Controllers\Controller;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PurchaseOrderController extends Controller
{
    public function index()
    {
        return $this->list(null);
    }

    /** Recepciones: órdenes ya recibidas. */
    public function receptions()
    {
        return $this->list('recibida');
    }

    protected function list(?string $status)
    {
        $companyId = $this->companyId();
        if (!$companyId) {
            return view('purchasing.orders.index', ['orders' => null, 'noCompany' => true, 'onlyReceived' => $status === 'recibida']);
        }

        $query = PurchaseOrder::with('provider')->where('company_id', $companyId)->latest('entry_date');
        if ($status) {
            $query->where('status', $status);
        }

        return view('purchasing.orders.index', [
            'orders' => $query->paginate(15),
            'noCompany' => false,
            'onlyReceived' => $status === 'recibida',
        ]);
    }

    public function create()
    {
        $companyId = $this->companyId();
        if (!$companyId) {
            return redirect()->route('purchase-orders.index');
        }

        return view('purchasing.orders.create', [
            'suppliers' => Supplier::where('company_id', $companyId)->where('active', true)->orderBy('name')->get(),
            'products' => Product::where('company_id', $companyId)->where('active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $companyId = $this->companyId();
        if (!$companyId) {
            return back()->withErrors(['error' => 'Debes operar dentro de una empresa.']);
        }

        $validated = $request->validate([
            'supplier_id'        => ['required', 'exists:suppliers,id'],
            'entry_date'         => ['nullable', 'date'],
            'notes'              => ['nullable', 'string'],
            'items'              => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.quantity'   => ['required', 'numeric', 'min:0.01'],
            'items.*.unit_cost'  => ['required', 'numeric', 'min:0'],
        ]);

        // Proveedor debe ser de la empresa.
        if (Supplier::whereKey($validated['supplier_id'])->where('company_id', $companyId)->doesntExist()) {
            return back()->withInput()->withErrors(['supplier_id' => 'El proveedor no pertenece a la empresa.']);
        }

        try {
            $order = DB::transaction(function () use ($validated, $companyId, $request) {
                $total = 0;
                $lines = [];
                foreach ($validated['items'] as $item) {
                    // El producto debe ser de la empresa.
                    if (Product::whereKey($item['product_id'])->where('company_id', $companyId)->doesntExist()) {
                        abort(422, 'Un producto no pertenece a la empresa.');
                    }
                    $lineTotal = (float) $item['quantity'] * (float) $item['unit_cost'];
                    $total += $lineTotal;
                    $lines[] = [
                        'product_id' => $item['product_id'],
                        'quantity'   => $item['quantity'],
                        'unit_cost'  => $item['unit_cost'],
                        'total'      => $lineTotal,
                    ];
                }

                $order = PurchaseOrder::create([
                    'company_id'   => $companyId,
                    'supplier_id'  => $validated['supplier_id'],
                    'warehouse_id' => null,
                    'entry_number' => $this->nextNumber($companyId),
                    'entry_date'   => $validated['entry_date'] ?? now(),
                    'notes'        => $validated['notes'] ?? null,
                    'status'       => 'pendiente',
                    'total'        => $total,
                    'created_by'   => auth()->id(),
                ]);

                foreach ($lines as $line) {
                    $order->items()->create($line);
                }

                return $order;
            });

            return redirect()->route('purchase-orders.show', $order)->with('success', 'Orden de compra creada: ' . $order->entry_number);
        } catch (\Throwable $e) {
            Log::error('Error al crear orden de compra', ['message' => $e->getMessage()]);
            return back()->withInput()->withErrors(['error' => 'No fue posible crear la orden.']);
        }
    }

    public function show(PurchaseOrder $purchaseOrder)
    {
        $this->authorizeOrder($purchaseOrder);
        $purchaseOrder->load(['provider', 'items.product']);

        return view('purchasing.orders.show', ['order' => $purchaseOrder]);
    }

    /** Recibe la orden: suma el stock de cada producto y registra movimientos. */
    public function receive(PurchaseOrder $purchaseOrder)
    {
        $this->authorizeOrder($purchaseOrder);

        if ($purchaseOrder->status !== 'pendiente') {
            return back()->withErrors(['error' => 'Solo se pueden recibir órdenes pendientes.']);
        }

        DB::transaction(function () use ($purchaseOrder) {
            foreach ($purchaseOrder->items()->with('product')->get() as $item) {
                $product = $item->product;
                if (!$product) {
                    continue;
                }

                $product->update([
                    'current_stock' => (float) $product->current_stock + (float) $item->quantity,
                    'cost' => $item->unit_cost, // actualiza costo al último de compra
                ]);

                InventoryMovement::create([
                    'company_id'    => $purchaseOrder->company_id,
                    'warehouse_id'  => null,
                    'branch_id'     => null,
                    'product_id'    => $product->id,
                    'user_id'       => auth()->id(),
                    'type'          => 'entrada',
                    'quantity'      => $item->quantity,
                    'unit_cost'     => $item->unit_cost,
                    'reference'     => $purchaseOrder->entry_number,
                    'notes'         => 'Recepción OC ' . $purchaseOrder->entry_number,
                    'movement_date' => now(),
                ]);
            }

            $purchaseOrder->update(['status' => 'recibida']);
        });

        return redirect()->route('purchase-orders.show', $purchaseOrder)->with('success', 'Orden recibida. Stock actualizado.');
    }

    public function void(PurchaseOrder $purchaseOrder)
    {
        $this->authorizeOrder($purchaseOrder);

        if ($purchaseOrder->status === 'recibida') {
            return back()->withErrors(['error' => 'No puedes anular una orden ya recibida.']);
        }

        $purchaseOrder->update(['status' => 'anulada']);

        return back()->with('success', 'Orden anulada.');
    }

    // ---- Helpers ----

    protected function companyId(): ?int
    {
        return auth()->user()->getCurrentCompany()?->id;
    }

    protected function nextNumber(int $companyId): string
    {
        $count = PurchaseOrder::withTrashed()->where('company_id', $companyId)->count() + 1;
        return 'OC-' . str_pad((string) $count, 6, '0', STR_PAD_LEFT);
    }

    protected function authorizeOrder(PurchaseOrder $order): void
    {
        if ($order->company_id !== $this->companyId()) {
            abort(403);
        }
    }
}
