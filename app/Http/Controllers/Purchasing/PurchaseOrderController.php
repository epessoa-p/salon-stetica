<?php

namespace App\Http\Controllers\Purchasing;

use App\Http\Controllers\Controller;
use App\Models\CashMovement;
use App\Models\InventoryMovement;
use App\Models\PaymentMethod;
use App\Models\Warehouse;
use App\Services\AccountService;
use App\Services\BranchResolver;
use App\Services\StockService;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Services\PayableService;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
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
            'payment_type'       => ['nullable', 'in:contado,credito'],
            'credit_days'        => ['nullable', 'required_if:payment_type,credito', 'integer', 'min:0', 'max:365'],
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
                    'payment_type' => $validated['payment_type'] ?? 'contado',
                    'credit_days'  => ($validated['payment_type'] ?? 'contado') === 'credito' ? (int) $validated['credit_days'] : null,
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
        $purchaseOrder->load(['provider', 'items.product', 'payable.payments']);

        return view('purchasing.orders.show', [
            'order'            => $purchaseOrder,
            'warehouses'       => Warehouse::with('branches:id,warehouse_id,name')->where('company_id', $purchaseOrder->company_id)->where('active', true)->orderBy('id')->get(),
            'defaultWarehouse' => app(StockService::class)->warehouseFor($purchaseOrder->company_id, app(BranchResolver::class)->defaultFor($purchaseOrder->company_id, auth()->id())),
            'paysOnReceipt'    => $this->paysOnReceipt($purchaseOrder),
            'sources'          => app(AccountService::class)->sourceOptions($purchaseOrder->company_id),
            'paymentMethods'   => PaymentMethod::optionsFor($purchaseOrder->company_id),
            'payment'          => $purchaseOrder->cash_movement_id ? CashMovement::withoutGlobalScopes()->with(['session.register', 'account'])->find($purchaseOrder->cash_movement_id) : null,
        ]);
    }

    /** Recibe la orden: suma el stock de cada producto y registra movimientos. */
    public function receive(Request $request, PurchaseOrder $purchaseOrder)
    {
        $this->authorizeOrder($purchaseOrder);
        $stock = app(StockService::class);
        $warehouseId = $request->integer('warehouse_id') ?: null;
        if ($warehouseId && !Warehouse::where('company_id', $purchaseOrder->company_id)->whereKey($warehouseId)->exists()) {
            return back()->withErrors(['error' => 'El almacén elegido no es de la empresa.']);
        }
        $warehouseId ??= $stock->warehouseFor($purchaseOrder->company_id, app(BranchResolver::class)->defaultFor($purchaseOrder->company_id, auth()->id()));

        if ($purchaseOrder->status !== 'pendiente') {
            return back()->withErrors(['error' => 'Solo se pueden recibir órdenes pendientes.']);
        }

        // Al contado: el dinero sale al recibir, de una caja abierta o de una cuenta.
        $sessionId = $accountId = null;
        $method = null;
        if ($this->paysOnReceipt($purchaseOrder)) {
            $methods = array_keys(PaymentMethod::optionsFor($purchaseOrder->company_id));
            $method = $request->validate(['payment_method' => ['nullable', Rule::in($methods)]])['payment_method'] ?? PaymentMethod::defaultCodeFor($purchaseOrder->company_id);
            try {
                [$sessionId, $accountId] = app(AccountService::class)->sourceFromRequest($request, $purchaseOrder->company_id);
            } catch (DomainException $e) {
                return back()->withErrors(['error' => $e->getMessage()]);
            }
        }

        try {
            DB::transaction(function () use ($purchaseOrder, $stock, $warehouseId, $sessionId, $accountId, $method) {
                foreach ($purchaseOrder->items()->with('product')->get() as $item) {
                    $product = $item->product;
                    if (!$product) {
                        continue;
                    }

                    $product->update(['cost' => $item->unit_cost]); // costo al último de compra
                    $stock->move($product->id, (float) $item->quantity, 'entrada', $purchaseOrder->company_id, $purchaseOrder->entry_number, 'Recepción OC ' . $purchaseOrder->entry_number, warehouseId: $warehouseId);
                }

                $purchaseOrder->update(['status' => 'recibida', 'warehouse_id' => $warehouseId]);

                // A crédito: la deuda con el proveedor nace al recibir la mercadería.
                if ($purchaseOrder->isOnCredit()) {
                    app(PayableService::class)->createFromOrder($purchaseOrder);
                }

                if ($method) {
                    $movement = app(AccountService::class)->spend($purchaseOrder->company_id, $sessionId, $accountId, [
                        'amount'         => (float) $purchaseOrder->total,
                        'concept'        => 'Compra ' . $purchaseOrder->entry_number . ' · ' . $purchaseOrder->supplierDisplay(),
                        'category'       => 'Pago a proveedores',
                        'expense_category_id' => \App\Models\ExpenseCategory::defaultFor((int) $purchaseOrder->company_id, 'proveedor')->id,
                        'movement_date'  => today(),
                        'payment_method' => $method,
                        'reference'      => $purchaseOrder->entry_number,
                        'supplier_id'    => $purchaseOrder->supplier_id,
                    ]);
                    $purchaseOrder->update(['cash_movement_id' => $movement->id]);
                }
            });
        } catch (DomainException $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }

        return redirect()->route('purchase-orders.show', $purchaseOrder)->with('success', 'Orden recibida. Stock actualizado.'
            . ($purchaseOrder->isOnCredit() ? ' Quedó registrada la cuenta por pagar al proveedor.' : ''));
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

    /** Compra al contado con monto, en un salón que lleva caja y cuentas (plan con finanzas). */
    protected function paysOnReceipt(PurchaseOrder $order): bool
    {
        return !$order->isOnCredit() && (float) $order->total > 0
            && (auth()->user()->is_super_admin || \App\Models\Company::find($order->company_id)?->planAllows('finance'));
    }

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
