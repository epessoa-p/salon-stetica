<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\CashMovement;
use App\Models\ExpenseCategory;
use Illuminate\Http\Request;

/** En qué se gasta, agrupado por tipo. */
class ExpenseCategoryController extends Controller
{
    public function index()
    {
        $companyId = $this->companyId();
        if (!$companyId) {
            return view('finance.expense_categories.index', ['noCompany' => true]);
        }
        ExpenseCategory::ensureDefaults($companyId);

        return view('finance.expense_categories.index', [
            'noCompany'  => false,
            'categories' => ExpenseCategory::forCompany($companyId)
                ->withCount(['expenses' => fn ($q) => $q->withoutGlobalScopes()->whereNull('cancelled_at')])
                ->ordered()->get()->groupBy(fn ($c) => $c->group()),
        ]);
    }

    public function store(Request $request)
    {
        $companyId = $this->companyId();
        abort_unless($companyId, 403);

        ExpenseCategory::create(ExpenseCategory::validateForm($request, $companyId) + [
            'company_id' => $companyId,
            'active'     => true,
            'sort_order' => (int) ExpenseCategory::forCompany($companyId)->max('sort_order') + 1,
        ]);

        return back()->with('success', 'Categoría creada.');
    }

    public function update(Request $request, ExpenseCategory $expenseCategory)
    {
        $this->authorizeCategory($expenseCategory);
        $expenseCategory->update(ExpenseCategory::validateForm($request, (int) $expenseCategory->company_id, $expenseCategory) + ['active' => $request->boolean('active')]);

        return back()->with('success', 'Categoría actualizada.');
    }

    /** Con gastos solo se da de baja, para no romper el historial. */
    public function destroy(ExpenseCategory $expenseCategory)
    {
        $this->authorizeCategory($expenseCategory);

        if (CashMovement::withoutGlobalScopes()->where('expense_category_id', $expenseCategory->id)->exists()) {
            $expenseCategory->update(['active' => false]);

            return back()->with('success', 'La categoría tiene gastos: se dio de baja.');
        }

        $expenseCategory->delete();

        return back()->with('success', 'Categoría eliminada.');
    }

    protected function authorizeCategory(ExpenseCategory $category): void
    {
        abort_if((int) $category->company_id !== (int) $this->companyId(), 403);
    }

    protected function companyId(): ?int
    {
        return auth()->user()->getCurrentCompany()?->id;
    }
}
