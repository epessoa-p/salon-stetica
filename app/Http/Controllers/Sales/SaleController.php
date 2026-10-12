<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\CashSession;
use App\Models\CompanySetting;
use App\Models\Client;
use App\Models\InventoryMovement;
use App\Models\PaymentMethod;
use App\Models\Personal;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Service;
use App\Services\CommissionService;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SaleController extends Controller
{
    public function __construct(protected CommissionService $commissions)
    {
    }

    /** Listado de ventas. */
    public function index(Request $request)
    {
        $companyId = $this->companyId();
        if (!$companyId) {
            return view('sales.sales.index', ['sales' => null, 'noCompany' => true]);
        }

        $sales = Sale::with(['client'])
            ->where('company_id', $companyId)
            ->latest('sale_date')
            ->paginate(15);

        return view('sales.sales.index', ['sales' => $sales, 'noCompany' => false]);
    }

    public function show(Sale $sale)
    {
        $this->authorizeSale($sale);
        $sale->load(['items.service', 'items.product', 'items.specialist', 'client', 'session.register', 'specialist', 'tipSettlement']);

        return view('sales.sales.show', ['sale' => $sale]);
    }

    /** Pantalla de Punto de Venta. */
    public function pos()
    {
        $companyId = $this->companyId();
        if (!$companyId) {
            return view('sales.pos', ['noCompany' => true]);
        }

        $setting = CompanySetting::current($companyId);
        $sellsProducts = $this->sellsProducts();

        return view('sales.pos', [
            'noCompany' => false,
            'paymentMethods' => PaymentMethod::optionsFor($companyId),
            'defaultPaymentMethod' => PaymentMethod::defaultCodeFor($companyId),
            'setting' => $setting,
            'clients' => Client::where('company_id', $companyId)->where('active', true)->orderBy('full_name')->get(),
            'services' => Service::where('company_id', $companyId)->where('active', true)->orderBy('name')->get(),
            'sellsProducts' => $sellsProducts,
            'products' => $sellsProducts
                ? Product::where('company_id', $companyId)->where('active', true)->orderBy('name')->get()
                : collect(),
            'branches' => Branch::where('company_id', $companyId)->where('active', true)->orderBy('name')->get(),
            'specialists' => Personal::where('company_id', $companyId)->where('active', true)->orderBy('full_name')->get(),
            'openSessions' => CashSession::with('register')
                ->where('status', 'open')
                ->whereHas('register', fn ($q) => $q->where('company_id', $companyId))
                ->get(),
        ]);
    }

    /** Registra una venta con sus ítems, su movimiento de caja, stock y comisiones. */
    public function store(Request $request)
    {
        $companyId = $this->companyId();
        if (!$companyId) {
            return back()->withErrors(['error' => 'Debes operar dentro de una empresa.']);
        }

        $validated = $request->validate([
            'client_id'       => ['nullable', 'exists:clients,id'],
            'client_name'     => ['nullable', 'string', 'max:255'],
            'personal_id'     => ['nullable', 'exists:personals,id'],
            'branch_id'       => ['nullable', 'exists:branches,id'],
            'cash_session_id' => ['nullable', 'exists:cash_sessions,id'],
            'payment_method'  => ['required', 'string', 'max:50', 'in:' . implode(',', array_keys(PaymentMethod::optionsFor($companyId)))],
            'global_discount' => ['nullable', 'numeric', 'min:0'],
            'tip'             => ['nullable', 'numeric', 'min:0'],
            'notes'           => ['nullable', 'string'],
            'items'           => ['required', 'array', 'min:1'],
            'items.*.service_id'  => ['nullable', 'exists:services,id'],
            'items.*.product_id'  => ['nullable', 'exists:products,id'],
            'items.*.description' => ['nullable', 'string', 'max:255'],
            'items.*.quantity'    => ['required', 'numeric', 'min:0.01'],
            'items.*.unit_price'  => ['required', 'numeric', 'min:0'],
            'items.*.discount'    => ['nullable', 'numeric', 'min:0'],
            'items.*.personal_id' => ['nullable', 'exists:personals,id'],
        ], [
            'items.required' => 'Agrega al menos un servicio o producto a la venta.',
        ]);

        // Preferencia: exigir una caja abierta para poder cobrar.
        if (CompanySetting::current($companyId)->require_cash_session && empty($validated['cash_session_id'])) {
            return back()->withInput()->withErrors([
                'cash_session_id' => 'Las preferencias de la empresa exigen registrar la venta en una caja abierta.',
            ]);
        }

        if ($error = $this->validateOwnership($validated, $companyId)) {
            return back()->withInput()->withErrors(['error' => $error]);
        }

        $tip = round((float) ($validated['tip'] ?? 0), 2);
        if ($tip > 0 && empty($validated['personal_id'])) {
            return back()->withInput()->withErrors([
                'personal_id' => 'La propina es del profesional principal: selecciona quién atendió la venta.',
            ]);
        }

        try {
            $sale = DB::transaction(function () use ($validated, $companyId, $tip) {
                $principalId = $validated['personal_id'] ?? null;

                // Totales
                $subtotal = 0;
                $lineDiscounts = 0;
                $lines = [];

                foreach ($validated['items'] as $item) {
                    $qty = (float) $item['quantity'];
                    $price = (float) $item['unit_price'];
                    $disc = (float) ($item['discount'] ?? 0);
                    $lineTotal = max(0, $qty * $price - $disc);
                    $subtotal += $qty * $price;
                    $lineDiscounts += $disc;

                    $productId = $item['product_id'] ?? null;
                    $serviceId = $productId ? null : ($item['service_id'] ?? null);

                    $lines[] = [
                        'service_id'  => $serviceId,
                        'product_id'  => $productId,
                        // Sin profesional en la línea, hereda el principal de la venta.
                        'personal_id' => ($item['personal_id'] ?? null) ?: $principalId,
                        'description' => ($item['description'] ?? null) ?: $this->lineName($serviceId, $productId),
                        'quantity'    => $qty,
                        'unit_price'  => $price,
                        'discount'    => $disc,
                        'total'       => $lineTotal,
                    ];
                }

                $globalDiscount = (float) ($validated['global_discount'] ?? 0);
                $totalDiscount = $lineDiscounts + $globalDiscount;
                $gravado = max(0, $subtotal - $totalDiscount);

                // Impuesto segun las preferencias de la empresa.
                $setting = CompanySetting::current($companyId);
                $tax = $setting->taxOn($gravado);
                $total = $setting->tax_included ? $gravado : $gravado + $tax;

                // Sesión de caja (opcional): debe estar abierta y ser de la empresa.
                $session = null;
                if (!empty($validated['cash_session_id'])) {
                    $session = CashSession::where('id', $validated['cash_session_id'])
                        ->where('status', 'open')
                        ->whereHas('register', fn ($q) => $q->where('company_id', $companyId))
                        ->first();
                }

                $number = $this->nextSaleNumber($companyId);

                $sale = Sale::create([
                    'company_id'      => $companyId,
                    'branch_id'       => $validated['branch_id'] ?? null,
                    'client_id'       => $validated['client_id'] ?? null,
                    'personal_id'     => $principalId,
                    'cash_session_id' => $session?->id,
                    'sale_number'     => $number,
                    'client_name'     => $validated['client_name'] ?? null,
                    'sale_date'       => now(),
                    'subtotal'        => $subtotal,
                    'tax'             => $tax,
                    'discount'        => $totalDiscount,
                    'total'           => $total,
                    'tip'             => $tip,
                    'payment_method'  => $validated['payment_method'],
                    'status'          => 'completada',
                    'notes'           => $validated['notes'] ?? null,
                    'created_by'      => auth()->id(),
                ]);

                foreach ($lines as $line) {
                    $sale->items()->create($line);

                    if ($line['product_id']) {
                        $this->moveStock((int) $line['product_id'], -$line['quantity'], 'salida', $sale, 'Venta ' . $number);
                    }
                }

                // Comisiones: se calculan ahora con las reglas vigentes y no se recalculan.
                $this->commissions->accrue($sale);

                // Ingreso en caja si hay sesión abierta. La propina va en un
                // movimiento aparte: entra a la caja pero no es venta del salón.
                if ($session) {
                    $session->movements()->create([
                        'type'           => 'ingreso',
                        'amount'         => $total,
                        'concept'        => 'Venta ' . $number,
                        'payment_method' => $validated['payment_method'],
                        'reference'      => (string) $sale->id,
                        'created_by'     => auth()->id(),
                    ]);

                    if ($tip > 0) {
                        $session->movements()->create([
                            'type'           => 'ingreso',
                            'amount'         => $tip,
                            'concept'        => 'Propina venta ' . $number,
                            'category'       => 'Propinas',
                            'payment_method' => $validated['payment_method'],
                            'reference'      => (string) $sale->id,
                            'created_by'     => auth()->id(),
                        ]);
                    }
                }

                return $sale;
            });

            return redirect()->route('sales.show', $sale)->with('success', 'Venta registrada: ' . $sale->sale_number);
        } catch (DomainException $e) {
            return back()->withInput()->withErrors(['error' => $e->getMessage()]);
        } catch (\Throwable $e) {
            Log::error('Error al registrar venta', ['message' => $e->getMessage()]);
            return back()->withInput()->withErrors(['error' => 'No fue posible registrar la venta.']);
        }
    }

    /**
     * Anula una venta: revierte caja (si la sesión sigue abierta), repone el
     * stock de los productos y ajusta las comisiones.
     */
    public function void(Sale $sale)
    {
        $this->authorizeSale($sale);

        if ($sale->isVoided()) {
            return back()->withErrors(['error' => 'La venta ya está anulada.']);
        }

        $reversals = DB::transaction(function () use ($sale) {
            $sale->update(['status' => 'anulada']);

            // Pendientes se borran; las ya liquidadas generan su negativo.
            $reversals = $this->commissions->reverseForVoidedSale($sale);

            foreach ($sale->items()->whereNotNull('product_id')->get() as $item) {
                $this->moveStock((int) $item->product_id, (float) $item->quantity, 'entrada', $sale, 'Anulación venta ' . $sale->sale_number);
            }

            $session = $sale->session;
            if ($session && $session->isOpen()) {
                $session->movements()->create([
                    'type'           => 'egreso',
                    'amount'         => $sale->total,
                    'concept'        => 'Anulación venta ' . $sale->sale_number,
                    'payment_method' => $sale->payment_method,
                    'reference'      => (string) $sale->id,
                    'created_by'     => auth()->id(),
                ]);

                // La propina se devuelve solo si todavía no se le pagó al profesional.
                if ((float) $sale->tip > 0 && !$sale->tip_settlement_id) {
                    $session->movements()->create([
                        'type'           => 'egreso',
                        'amount'         => $sale->tip,
                        'concept'        => 'Anulación propina venta ' . $sale->sale_number,
                        'category'       => 'Propinas',
                        'payment_method' => $sale->payment_method,
                        'reference'      => (string) $sale->id,
                        'created_by'     => auth()->id(),
                    ]);
                }
            }

            return $reversals;
        });

        $message = 'Venta anulada.';
        if ($reversals > 0) {
            $message .= " {$reversals} comisión(es) ya liquidada(s) quedaron en pie: se generó su reverso negativo,"
                . ' que se descontará en la próxima liquidación del profesional.';
        }
        if ((float) $sale->tip > 0 && $sale->tip_settlement_id) {
            $message .= ' La propina ya se había pagado al profesional; regularízala con él.';
        }

        return back()->with('success', $message);
    }

    // ---- Helpers ----

    /** ¿El plan incluye inventario? Solo entonces el POS vende productos. */
    protected function sellsProducts(): bool
    {
        return (bool) auth()->user()->getCurrentCompany()?->planAllows('inventory');
    }

    /**
     * Las reglas "exists" no miran la empresa: se verifica que servicios,
     * productos y profesionales sean de la empresa activa, y que cada línea
     * tenga un servicio, un producto o una descripción.
     */
    protected function validateOwnership(array $data, int $companyId): ?string
    {
        $items = collect($data['items']);
        $serviceIds = $items->pluck('service_id')->filter()->unique();
        $productIds = $items->pluck('product_id')->filter()->unique();
        $personalIds = $items->pluck('personal_id')->push($data['personal_id'] ?? null)->filter()->unique();

        foreach ($items as $item) {
            if (empty($item['service_id']) && empty($item['product_id']) && empty($item['description'])) {
                return 'Cada línea debe tener un servicio, un producto o una descripción.';
            }
        }

        if ($productIds->isNotEmpty() && !$this->sellsProducts()) {
            return 'El plan de la empresa no incluye inventario: no se pueden vender productos.';
        }

        $owns = fn (string $model, $ids) => $ids->isEmpty()
            || $model::withoutGlobalScopes()->where('company_id', $companyId)->whereIn('id', $ids)->count() === $ids->count();

        if (!$owns(Service::class, $serviceIds) || !$owns(Product::class, $productIds) || !$owns(Personal::class, $personalIds)) {
            return 'La venta incluye servicios, productos o profesionales que no pertenecen a la empresa.';
        }

        return null;
    }

    protected function lineName($serviceId, $productId): ?string
    {
        if ($productId) {
            return Product::find($productId)?->name;
        }

        return $serviceId ? Service::find($serviceId)?->name : null;
    }

    /**
     * Mueve el stock de un producto y deja el movimiento de inventario.
     * $delta negativo = sale stock. Sin stock suficiente se cancela la venta.
     */
    protected function moveStock(int $productId, float $delta, string $type, Sale $sale, string $note): void
    {
        $product = Product::whereKey($productId)->lockForUpdate()->first();
        if (!$product) {
            return;
        }

        $current = (float) $product->current_stock;
        if ($delta < 0 && abs($delta) > $current) {
            throw new DomainException("No hay stock suficiente de {$product->name}. Disponible: " . rtrim(rtrim(number_format($current, 2), '0'), '.'));
        }

        $product->update(['current_stock' => max(0, $current + $delta)]);

        InventoryMovement::create([
            'company_id'    => $sale->company_id,
            'product_id'    => $product->id,
            'user_id'       => auth()->id(),
            'type'          => $type,
            'quantity'      => abs($delta),
            'unit_cost'     => $product->cost,
            'reference'     => $sale->sale_number,
            'notes'         => $note,
            'movement_date' => now(),
        ]);
    }

    protected function companyId(): ?int
    {
        return auth()->user()->getCurrentCompany()?->id;
    }

    protected function nextSaleNumber(int $companyId): string
    {
        $count = Sale::withTrashed()->where('company_id', $companyId)->count() + 1;

        return CompanySetting::current($companyId)->folio($count);
    }

    protected function authorizeSale(Sale $sale): void
    {
        if ($sale->company_id !== $this->companyId()) {
            abort(403);
        }
    }
}
