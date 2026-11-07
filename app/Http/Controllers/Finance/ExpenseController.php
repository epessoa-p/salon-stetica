<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\CashMovement;
use App\Models\CompanySetting;
use App\Models\ExpenseCategory;
use App\Models\Personal;
use App\Models\Supplier;
use App\Services\AccountService;
use App\Services\BasicServicesMonth;
use App\Services\CommissionService;
use App\Services\ExpenseRegistrar;
use DomainException;
use Illuminate\Http\Request;

/** Gastos por pestañas: generales, a proveedores, a personal y servicios básicos. */
class ExpenseController extends Controller
{
    public function __construct(protected ExpenseRegistrar $registrar)
    {
    }

    public function index(Request $request)
    {
        $companyId = $this->companyId();
        if (!$companyId) {
            return view('finance.expenses.index', ['noCompany' => true]);
        }
        ExpenseCategory::ensureDefaults($companyId);

        $group = array_key_exists((string) $request->input('group'), ExpenseCategory::TYPES) ? $request->input('group') : null;
        $from = $request->input('from', today()->startOfMonth()->toDateString());
        $to = $request->input('to', today()->toDateString());
        $categoryId = $request->integer('category') ?: null;
        $source = $request->input('source');

        $query = CashMovement::with(['session.register', 'account', 'supplierRef', 'personal', 'expenseCategory'])
            ->notTransfer()->where('company_id', $companyId)->where('type', 'egreso')
            ->whereBetween('movement_date', [$from, $to])
            ->inExpenseGroup($group)
            ->when($categoryId, fn ($q) => $q->where('expense_category_id', $categoryId))
            ->when($source === 'caja', fn ($q) => $q->whereNotNull('cash_session_id'))
            ->when($source === 'cuenta', fn ($q) => $q->whereNotNull('financial_account_id'))
            ->when($source === 'ninguno', fn ($q) => $q->whereNull('cash_session_id')->whereNull('financial_account_id'));

        $company = auth()->user()->getCurrentCompany();
        $showOwed = $group === 'personal' && $company->planAllows('commissions') && auth()->user()->hasPermissionInCompany('commissions.view', $company);

        return view('finance.expenses.index', [
            'noCompany'  => false,
            'group'      => $group,
            'total'      => (float) (clone $query)->live()->sum('amount'),
            'expenses'   => $query->orderByDesc('movement_date')->orderByDesc('id')->paginate(20)->withQueryString(),
            'filters'    => ['from' => $from, 'to' => $to, 'category' => $categoryId, 'source' => $source],
            'categories' => ExpenseCategory::forCompany($companyId)->inGroup($group)->ordered()->get(),
            'setting'    => CompanySetting::current($companyId),
            'basics'     => $group === 'basicos' ? app(BasicServicesMonth::class)->for($companyId, BasicServicesMonth::parse($request->input('month'))) : null,
            'owed'       => $showOwed ? app(CommissionService::class)->pendingAll($companyId) : null,
        ]);
    }

    public function create(Request $request)
    {
        $companyId = $this->companyId();
        if (!$companyId) {
            return redirect()->route('expenses.index');
        }
        ExpenseCategory::ensureDefaults($companyId);
        $group = array_key_exists((string) $request->input('group'), ExpenseCategory::TYPES) ? $request->input('group') : 'general';

        return view('finance.expenses.create', [
            'group'           => $group,
            'categories'      => ExpenseCategory::forCompany($companyId)->active()->inGroup($group)
                ->when($group === 'personal', fn ($q) => $q->where('name', '!=', ExpenseCategory::COMMISSIONS))->ordered()->get(),
            'defaultCategory' => $request->integer('category') ?: (in_array($group, ['proveedor', 'personal'], true) ? ExpenseCategory::defaultFor($companyId, $group)->id : null),
            'presetAmount'    => $request->input('amount'),
            'selectedAccount' => $request->integer('account') ?: null,
            'suppliers'       => Supplier::where('company_id', $companyId)->where('active', true)->orderBy('name')->get(['id', 'name']),
            'people'          => Personal::where('company_id', $companyId)->where('active', true)->orderBy('full_name')->get(['id', 'full_name']),
            'branches'        => Branch::where('company_id', $companyId)->where('active', true)->orderBy('name')->get(['id', 'name']),
            'sources'         => app(AccountService::class)->sourceOptions($companyId),
            'setting'         => CompanySetting::current($companyId),
        ]);
    }

    public function store(Request $request)
    {
        $companyId = $this->companyId();
        if (!$companyId) {
            return back()->withErrors(['error' => 'Debes operar dentro de una empresa.']);
        }

        try {
            $expense = $this->registrar->register($request, $companyId);
        } catch (DomainException $e) {
            return back()->withInput()->withErrors(['error' => $e->getMessage()]);
        }

        return redirect()->route('expenses.index', ['group' => $expense->expenseCategory?->group()])
            ->with('success', 'Gasto ' . $expense->number . ' registrado.');
    }

    public function show(CashMovement $expense)
    {
        $this->authorizeExpense($expense);
        $expense->load(['session.register', 'account', 'supplierRef', 'personal', 'expenseCategory']);

        return view('finance.expenses.show', ['expense' => $expense, 'setting' => CompanySetting::current($expense->company_id)]);
    }

    public function cancel(Request $request, CashMovement $expense)
    {
        $this->authorizeExpense($expense);
        $data = $request->validate(['cancel_reason' => ['nullable', 'string', 'max:255']]);

        try {
            $this->registrar->cancel($expense, $data['cancel_reason'] ?? null);
        } catch (DomainException $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }

        return back()->with('success', 'Gasto anulado: el dinero volvió a su origen.');
    }

    protected function authorizeExpense(CashMovement $expense): void
    {
        abort_if((int) $expense->company_id !== (int) $this->companyId() || $expense->type !== 'egreso', 403);
    }

    protected function companyId(): ?int
    {
        return auth()->user()->getCurrentCompany()?->id;
    }
}
