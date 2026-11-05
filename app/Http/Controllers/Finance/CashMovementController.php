<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\CashMovement;
use App\Models\PaymentMethod;
use App\Models\Personal;
use App\Models\Supplier;
use App\Services\AccountService;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Ingresos y Gastos. El tipo llega desde la ruta (->defaults('type', ...)),
 * así un mismo controlador sirve a ambos módulos.
 */
class CashMovementController extends Controller
{
    public function __construct(protected AccountService $accounts)
    {
    }

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

        $source = $request->input('source');

        $query = CashMovement::with(['session.register', 'account', 'supplierRef', 'personal'])
            ->notTransfer()
            ->where('type', $type)
            ->whereBetween('movement_date', [$from, $to])
            ->orderByDesc('movement_date')->orderByDesc('id');

        if ($category) {
            $query->where('category', $category);
        }

        $query->when($source === 'caja', fn ($q) => $q->whereNotNull('cash_session_id'))
            ->when($source === 'cuenta', fn ($q) => $q->whereNotNull('financial_account_id'))
            ->when($source === 'ninguno', fn ($q) => $q->whereNull('cash_session_id')->whereNull('financial_account_id'));

        $total = (float) (clone $query)->sum('amount');

        return view('finance.movements.index', $this->viewData($type, [
            'movements' => $query->paginate(20)->withQueryString(),
            'noCompany' => false,
            'total' => $total,
            'filters' => compact('from', 'to', 'category', 'source'),
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
            'sources' => $this->accounts->sourceOptions($companyId),
            'selectedAccount' => $request->integer('account') ?: null,
            'suppliers' => $type === 'egreso' ? Supplier::where('company_id', $companyId)->orderBy('name')->get(['id', 'name']) : collect(),
            'people' => $type === 'egreso' ? Personal::where('company_id', $companyId)->where('active', true)->orderBy('full_name')->get(['id', 'full_name']) : collect(),
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
            'supplier_id'     => ['nullable', Rule::exists('suppliers', 'id')->where('company_id', $companyId)],
            'personal_id'     => ['nullable', Rule::exists('personals', 'id')->where('company_id', $companyId)],
        ]);

        // Todo gasto sale de una caja abierta o de una cuenta; un ingreso puede solo registrarse.
        try {
            [$sessionId, $accountId] = $this->accounts->sourceFromRequest($request, $companyId, $type === 'egreso');
            $method = $type === 'egreso' ? 'spend' : 'receive';
            $this->accounts->{$method}($companyId, $sessionId, $accountId, $validated);
        } catch (DomainException $e) {
            return back()->withInput()->withErrors(['error' => $e->getMessage()]);
        }

        $route = $type === 'ingreso' ? 'incomes.index' : 'expenses.index';
        $label = $type === 'ingreso' ? 'Ingreso registrado.' : 'Gasto registrado.';

        return redirect()->route($route)->with('success', $label);
    }

    public function destroy(Request $request, CashMovement $movement)
    {
        if ($movement->company_id !== $this->companyId()) {
            abort(403);
        }

        // Lo generado por una venta, pago, compra o traspaso se anula desde su origen.
        if ($movement->isGenerated()) {
            return back()->withErrors(['error' => 'Este movimiento proviene de otro módulo (venta, pago o compra): anúlalo desde su origen.']);
        }

        $type = $movement->type;
        $this->accounts->voidCashMovement($movement->id);

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
