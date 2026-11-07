<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Branch;
use App\Models\CashMovement;
use App\Models\CompanySetting;
use App\Models\ExpenseCategory;
use App\Models\FinancialAccount;
use App\Models\Personal;
use App\Models\Supplier;
use App\Services\AccountService;
use App\Services\BasicServicesMonth;
use App\Services\CommissionService;
use App\Services\ExpenseRegistrar;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/** Gastos, categorías, servicios básicos y comisiones por pagar: mismas reglas que la web. */
class ExpensesController extends ApiController
{
    public function __construct(protected AccountService $accounts, protected ExpenseRegistrar $registrar)
    {
    }

    /** Lo que necesitan los formularios; con `group`, solo las categorías de ese grupo. */
    public function options(Request $request): JsonResponse
    {
        if ($r = $this->planMissing('finance')) {
            return $r;
        }
        if (!$this->can('accounts.view') && !$this->can('expenses.create')) {
            return $this->forbidden();
        }

        $companyId = $this->company()->id;
        ExpenseCategory::ensureDefaults($companyId);
        $group = $request->input('group');
        $sources = $this->accounts->sourceOptions($companyId);
        $forExpense = $this->can('expenses.create');

        return response()->json([
            'currency'    => CompanySetting::current($companyId)->currency_symbol,
            'types'       => collect(FinancialAccount::TYPES)->map(fn ($label, $code) => ['code' => $code, 'label' => $label])->values(),
            'categories'  => ExpenseCategory::forCompany($companyId)->active()->inGroup($group)
                ->where(fn ($q) => $group === 'personal' ? $q->where('name', '!=', ExpenseCategory::COMMISSIONS) : $q)
                ->ordered()->get()->map(fn ($c) => $this->categoryRow($c))->values(),
            'default_category_id' => in_array($group, ['proveedor', 'personal'], true) ? ExpenseCategory::defaultFor($companyId, $group)->id : null,
            'sessions'    => $sources['sessions']->map(fn ($s) => ['id' => $s->id, 'name' => $s->register?->name ?? 'Caja', 'expected' => round($s->expectedBalance(), 2)])->values(),
            'accounts'    => $sources['accounts']->map(fn ($a) => ['id' => $a->id, 'name' => $a->name, 'balance' => round((float) $a->saldo, 2)])->values(),
            'can_use_accounts' => $sources['canUseAccounts'],
            'suppliers'   => $forExpense ? Supplier::where('company_id', $companyId)->where('active', true)->orderBy('name')->get(['id', 'name'])->map(fn ($s) => ['id' => $s->id, 'name' => $s->name])->values() : [],
            'people'      => $forExpense ? Personal::where('company_id', $companyId)->where('active', true)->orderBy('full_name')->get(['id', 'full_name'])->map(fn ($p) => ['id' => $p->id, 'name' => $p->full_name])->values() : [],
            'branches'    => Branch::where('company_id', $companyId)->where('active', true)->orderBy('name')->get(['id', 'name'])->map(fn ($b) => ['id' => $b->id, 'name' => $b->name])->values(),
            'can_create_supplier' => $this->can('suppliers.create'),
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        if ($r = $this->guard('expenses.view')) {
            return $r;
        }

        $data = $request->validate([
            'from'  => ['nullable', 'date'],
            'to'    => ['nullable', 'date', 'after_or_equal:from'],
            'group' => ['nullable', 'in:general,proveedor,personal,basicos'],
        ], ['to.after_or_equal' => 'La fecha final debe ser igual o posterior a la inicial.']);

        $companyId = $this->company()->id;
        $from = $data['from'] ?? today()->startOfMonth()->toDateString();
        $to = $data['to'] ?? today()->toDateString();

        $query = CashMovement::with(['session.register', 'account', 'supplierRef', 'personal', 'expenseCategory'])
            ->notTransfer()->where('company_id', $companyId)->where('type', 'egreso')
            ->whereBetween('movement_date', [$from, $to])
            ->inExpenseGroup($data['group'] ?? null);

        $canCancel = $this->can('expenses.delete');

        return response()->json([
            'currency' => CompanySetting::current($companyId)->currency_symbol,
            'from'     => $from,
            'to'       => $to,
            // Sobre todo el filtro, no sobre lo que se devuelve, y sin los anulados.
            'total'    => round((float) (clone $query)->live()->sum('amount'), 2),
            'expenses' => $query->orderByDesc('movement_date')->orderByDesc('id')->limit(100)->get()
                ->map(fn (CashMovement $m) => $this->row($m, $canCancel))->values(),
            'can'      => ['create' => $this->can('expenses.create')],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        if ($r = $this->guard('expenses.create')) {
            return $r;
        }

        try {
            $movement = $this->registrar->register($request, $this->company()->id);
        } catch (DomainException $e) {
            return $this->fail(422, 'invalid_operation', $e->getMessage());
        }

        return response()->json(['message' => 'Gasto ' . $movement->number . ' registrado.', 'id' => $movement->id, 'number' => $movement->number], 201);
    }

    public function cancel(Request $request, CashMovement $movement): JsonResponse
    {
        if ($r = $this->guard('expenses.delete')) {
            return $r;
        }
        if ((int) $movement->company_id !== $this->company()->id) {
            return $this->fail(404, 'not_found', 'Gasto no encontrado.');
        }
        $data = $request->validate(['cancel_reason' => ['nullable', 'string', 'max:255']]);

        try {
            $this->registrar->cancel($movement, $data['cancel_reason'] ?? null);
        } catch (DomainException $e) {
            return $this->fail(422, 'invalid_operation', $e->getMessage());
        }

        return response()->json(['message' => $movement->financial_account_id ? 'Gasto anulado: el dinero volvió a la cuenta.' : 'Gasto anulado.']);
    }

    public function basics(Request $request): JsonResponse
    {
        if ($r = $this->guard('expenses.view')) {
            return $r;
        }

        return response()->json(app(BasicServicesMonth::class)->for($this->company()->id, BasicServicesMonth::parse($request->input('month'))));
    }

    // ---- Categorías ----

    public function categories(): JsonResponse
    {
        if ($r = $this->guard('expense_categories.view')) {
            return $r;
        }

        $companyId = $this->company()->id;
        ExpenseCategory::ensureDefaults($companyId);
        $canDelete = $this->can('expense_categories.delete');

        return response()->json([
            'categories' => ExpenseCategory::forCompany($companyId)->withCount(['expenses' => fn ($q) => $q->withoutGlobalScopes()->whereNull('cancelled_at')])->ordered()->get()
                ->map(fn ($c) => $this->categoryRow($c) + ['expenses_count' => (int) $c->expenses_count, 'can_delete' => $canDelete])->values(),
            'can' => ['create' => $this->can('expense_categories.create'), 'edit' => $this->can('expense_categories.edit'), 'delete' => $canDelete],
        ]);
    }

    public function storeCategory(Request $request): JsonResponse
    {
        if ($r = $this->guard('expense_categories.create')) {
            return $r;
        }

        $companyId = $this->company()->id;
        $data = ExpenseCategory::validateForm($request, $companyId);
        $category = ExpenseCategory::create($data + [
            'company_id' => $companyId,
            'active'     => true,
            'sort_order' => (int) ExpenseCategory::forCompany($companyId)->max('sort_order') + 1,
        ]);

        return response()->json(['message' => 'Categoría creada.', 'category' => $this->categoryRow($category)], 201);
    }

    public function updateCategory(Request $request, ExpenseCategory $category): JsonResponse
    {
        if ($r = $this->guard('expense_categories.edit')) {
            return $r;
        }
        if ((int) $category->company_id !== $this->company()->id) {
            return $this->fail(404, 'not_found', 'Categoría no encontrada.');
        }

        $category->update(ExpenseCategory::validateForm($request, $category->company_id, $category) + ['active' => $request->boolean('active', true)]);

        return response()->json(['message' => 'Categoría actualizada.', 'category' => $this->categoryRow($category)]);
    }

    /** Con gastos solo se da de baja, para no romper el historial. */
    public function destroyCategory(ExpenseCategory $category): JsonResponse
    {
        if ($r = $this->guard('expense_categories.delete')) {
            return $r;
        }
        if ((int) $category->company_id !== $this->company()->id) {
            return $this->fail(404, 'not_found', 'Categoría no encontrada.');
        }

        if (CashMovement::withoutGlobalScopes()->where('expense_category_id', $category->id)->exists()) {
            $category->update(['active' => false]);

            return response()->json(['message' => 'La categoría tiene gastos: se dio de baja.']);
        }

        $category->delete();

        return response()->json(['message' => 'Categoría eliminada.']);
    }

    // ---- Proveedores ----

    /** Alta rápida de proveedor desde el pago. */
    public function storeSupplier(Request $request): JsonResponse
    {
        if ($r = $this->planMissing('finance')) {
            return $r;
        }
        if (!$this->can('suppliers.create')) {
            return $this->forbidden();
        }

        $data = $request->validate([
            'name'  => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
        ], ['name.required' => 'Escribe el nombre del proveedor.']);

        $supplier = Supplier::create(['company_id' => $this->company()->id, 'name' => mb_strtoupper(trim($data['name'])), 'phone' => $data['phone'] ?? null, 'active' => true, 'created_by' => auth()->id()]);

        return response()->json(['id' => $supplier->id, 'name' => $supplier->name], 201);
    }

    // ---- Comisiones por pagar ----

    /** Lo que se debe a cada persona, de cualquier fecha. */
    public function pendingCommissions(): JsonResponse
    {
        if ($r = $this->planMissing('commissions')) {
            return $r;
        }
        if (!$this->can('commissions.view')) {
            return $this->forbidden();
        }

        $rows = app(CommissionService::class)->pendingAll($this->company()->id);

        return response()->json([
            'people' => $rows,
            'total'  => round(collect($rows)->sum('total'), 2),
            'count'  => collect($rows)->sum('count'),
            'can'    => ['pay' => $this->can('commissions.pay')],
        ]);
    }

    public function settle(Request $request, Personal $personal): JsonResponse
    {
        if ($r = $this->planMissing('commissions')) {
            return $r;
        }
        if (!$this->can('commissions.pay')) {
            return $this->forbidden();
        }
        $companyId = $this->company()->id;
        if ((int) $personal->company_id !== $companyId) {
            return $this->fail(404, 'not_found', 'Personal no encontrado.');
        }

        $data = $request->validate([
            'period_start' => ['required', 'date'],
            'period_end'   => ['required', 'date', 'after_or_equal:period_start'],
            'include_tips' => ['nullable', 'boolean'],
            'notes'        => ['nullable', 'string', 'max:1000'],
        ], ['period_end.after_or_equal' => 'La fecha fin no puede ser anterior a la de inicio.']);

        try {
            [$sessionId, $accountId] = $this->accounts->sourceFromRequest($request, $companyId, false);
            $settlement = app(CommissionService::class)->settle(
                $personal,
                Carbon::parse($data['period_start']),
                Carbon::parse($data['period_end']),
                $request->boolean('include_tips'),
                $sessionId,
                $data['notes'] ?? null,
                auth()->user(),
                $accountId,
            );
        } catch (DomainException $e) {
            return $this->fail(422, 'invalid_operation', $e->getMessage());
        }

        return response()->json(['message' => "Liquidación {$settlement->number} pagada.", 'number' => $settlement->number], 201);
    }

    // ---- Helpers ----

    protected function guard(string $permission): ?JsonResponse
    {
        if ($r = $this->planMissing('finance')) {
            return $r;
        }

        return $this->can($permission) ? null : $this->forbidden();
    }

    protected function categoryRow(ExpenseCategory $c): array
    {
        return [
            'id'        => $c->id,
            'name'      => $c->name,
            'type'      => $c->group(),
            'frequency' => $c->recurring ? ($c->frequency ?: 'mensual') : null,
            'active'    => (bool) $c->active,
        ];
    }

    protected function row(CashMovement $m, bool $canCancel): array
    {
        return [
            'id'            => $m->id,
            'number'        => $m->number,
            'date'          => optional($m->movement_date)->toDateString(),
            'concept'       => $m->concept,
            'category'      => $m->expenseCategory?->name ?? $m->category,
            'amount'        => (float) $m->amount,
            'reference'     => $m->reference,
            'notes'         => $m->notes,
            'source'        => $m->account ? ['type' => 'cuenta', 'name' => $m->account->name]
                : ($m->session ? ['type' => 'caja', 'name' => $m->session->register?->name ?? 'Caja'] : null),
            'paid_to'       => $m->supplierRef?->name ?? $m->personal?->full_name,
            'cancelled'     => $m->isCancelled(),
            'cancel_reason' => $m->cancel_reason,
            'can_cancel'    => $canCancel && !$m->isCancelled() && !$m->isGenerated(),
        ];
    }
}
