<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\CashMovement;
use App\Models\CashSession;
use App\Models\PaymentMethod;
use Illuminate\Http\Request;

/**
 * Ingresos y Gastos. El tipo llega desde la ruta (->defaults('type', ...)),
 * así un mismo controlador sirve a ambos módulos.
 */
class CashMovementController extends Controller
{
    public function index(Request $request)
    {
        $type = $this->type($request);
        $companyId = $this->companyId();

        if (!$companyId) {
            return view('finance.movements.index', $this->viewData($type, ['movements' => null, 'noCompany' => true]));
        }

        $from = $request->input('from', today()->startOfMonth()->toDateString());
        $to   = $request->input('to', today()->toDateString());
        $category = $request->input('category');

        $query = CashMovement::with('session.register')
            ->where('type', $type)
            ->whereBetween('movement_date', [$from, $to])
            ->orderByDesc('movement_date')->orderByDesc('id');

        if ($category) {
            $query->where('category', $category);
        }

        $total = (float) (clone $query)->sum('amount');

        return view('finance.movements.index', $this->viewData($type, [
            'movements' => $query->paginate(20)->withQueryString(),
            'noCompany' => false,
            'total' => $total,
            'filters' => compact('from', 'to', 'category'),
        ]));
    }

    public function create(Request $request)
    {
        $type = $this->type($request);
        $companyId = $this->companyId();

        if (!$companyId) {
            return redirect()->route($type === 'ingreso' ? 'incomes.index' : 'expenses.index');
        }

        return view('finance.movements.create', $this->viewData($type, [
            'openSessions' => CashSession::with('register')
                ->where('status', 'open')
                ->whereHas('register', fn ($q) => $q->where('company_id', $companyId))
                ->get(),
        ]));
    }

    public function store(Request $request)
    {
        $type = $this->type($request);
        $companyId = $this->companyId();

        if (!$companyId) {
            return back()->withErrors(['error' => 'Debes operar dentro de una empresa.']);
        }

        $validated = $request->validate([
            'amount'          => ['required', 'numeric', 'min:0.01'],
            'concept'         => ['required', 'string', 'max:255'],
            'category'        => ['nullable', 'string', 'max:100'],
            'movement_date'   => ['required', 'date'],
            'payment_method'  => ['required', 'string', 'max:30', 'in:' . implode(',', array_keys(PaymentMethod::optionsFor()))],
            'reference'       => ['nullable', 'string', 'max:255'],
            'cash_session_id' => ['nullable', 'exists:cash_sessions,id'],
        ]);

        // Si se elige una caja, debe estar abierta y pertenecer a la empresa.
        $sessionId = null;
        if (!empty($validated['cash_session_id'])) {
            $sessionId = CashSession::where('id', $validated['cash_session_id'])
                ->where('status', 'open')
                ->whereHas('register', fn ($q) => $q->where('company_id', $companyId))
                ->value('id');

            if (!$sessionId) {
                return back()->withInput()->withErrors(['cash_session_id' => 'La caja seleccionada no está abierta o no pertenece a la empresa.']);
            }
        }

        CashMovement::create([
            'company_id'      => $companyId,
            'cash_session_id' => $sessionId,
            'type'            => $type,
            'amount'          => $validated['amount'],
            'concept'         => $validated['concept'],
            'category'        => $validated['category'] ?? null,
            'movement_date'   => $validated['movement_date'],
            'payment_method'  => $validated['payment_method'],
            'reference'       => $validated['reference'] ?? null,
            'created_by'      => auth()->id(),
        ]);

        $route = $type === 'ingreso' ? 'incomes.index' : 'expenses.index';
        $label = $type === 'ingreso' ? 'Ingreso registrado.' : 'Gasto registrado.';

        return redirect()->route($route)->with('success', $label);
    }

    public function destroy(Request $request, CashMovement $movement)
    {
        if ($movement->company_id !== $this->companyId()) {
            abort(403);
        }

        // Los movimientos generados por una venta no se borran desde aquí.
        if ($movement->reference && str_starts_with((string) $movement->concept, 'Venta ')) {
            return back()->withErrors(['error' => 'Este movimiento proviene de una venta; anula la venta en su lugar.']);
        }

        $type = $movement->type;
        $movement->delete();

        return redirect()->route($type === 'ingreso' ? 'incomes.index' : 'expenses.index')
            ->with('success', 'Movimiento eliminado.');
    }

    // ---- Helpers ----

    protected function type(Request $request): string
    {
        return str_starts_with((string) $request->route()?->getName(), 'incomes.') ? 'ingreso' : 'egreso';
    }

    protected function companyId(): ?int
    {
        return auth()->user()->getCurrentCompany()?->id;
    }

    /** Textos y rutas según el tipo, para reutilizar las vistas. */
    protected function viewData(string $type, array $extra = []): array
    {
        $isIncome = $type === 'ingreso';

        return array_merge([
            'type' => $type,
            'title' => $isIncome ? 'Ingresos' : 'Gastos',
            'singular' => $isIncome ? 'ingreso' : 'gasto',
            'icon' => $isIncome ? 'bi-arrow-down-circle' : 'bi-arrow-up-circle',
            'color' => $isIncome ? 'success' : 'danger',
            'indexRoute' => $isIncome ? 'incomes.index' : 'expenses.index',
            'createRoute' => $isIncome ? 'incomes.create' : 'expenses.create',
            'storeRoute' => $isIncome ? 'incomes.store' : 'expenses.store',
            'destroyRoute' => $isIncome ? 'incomes.destroy' : 'expenses.destroy',
            'categories' => $isIncome ? config('saas.income_categories', []) : config('saas.expense_categories', []),
        ], $extra);
    }
}
