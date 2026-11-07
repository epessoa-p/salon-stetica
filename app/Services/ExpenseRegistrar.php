<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\CashMovement;
use App\Models\ExpenseCategory;
use App\Models\PaymentMethod;
use App\Models\Personal;
use App\Models\Supplier;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Registrar y anular gastos. Lo usan la web y la API: las reglas viven aquí.
 * Un gasto no se edita: se anula y se vuelve a registrar.
 */
class ExpenseRegistrar
{
    public function __construct(protected AccountService $accounts, protected BranchResolver $branches)
    {
    }

    /** @throws DomainException|\Illuminate\Validation\ValidationException */
    public function register(Request $request, int $companyId): CashMovement
    {
        ExpenseCategory::ensureDefaults($companyId);

        // Desde Proveedores o Personal no se elige la categoría: va la del grupo.
        $group = $request->input('group');
        if (in_array($group, ['proveedor', 'personal'], true) && !$request->filled('expense_category_id')) {
            $request->merge(['expense_category_id' => ExpenseCategory::defaultFor($companyId, $group)->id]);
        }

        // Lo que no hace falta describir se completa solo.
        $category = ExpenseCategory::forCompany($companyId)->find($request->input('expense_category_id'));
        if ($category && !$request->filled('concept') && ($default = $this->defaultDescription($category, $request))) {
            $request->merge(['concept' => $default]);
        }

        $data = $request->validate([
            'expense_category_id' => ['required', Rule::exists('expense_categories', 'id')->where('company_id', $companyId)->whereNull('deleted_at')],
            'supplier_id'   => ['nullable', Rule::exists('suppliers', 'id')->where('company_id', $companyId)],
            'personal_id'   => ['nullable', Rule::exists('personals', 'id')->where('company_id', $companyId)],
            'branch_id'     => ['nullable', Rule::exists('branches', 'id')->where('company_id', $companyId)],
            'concept'       => ['required', 'string', 'max:255'],
            'amount'        => ['required', 'numeric', 'min:0.01'],
            'movement_date' => ['required', 'date', 'before_or_equal:today'],
            'reference'     => ['nullable', 'string', 'max:255'],
            'notes'         => ['nullable', 'string', 'max:2000'],
        ], [
            'expense_category_id.required' => 'Elige en qué se gastó.',
            'expense_category_id.exists'   => 'Elige en qué se gastó.',
            'concept.required'             => 'Describe el gasto: sin descripción, después no se entiende.',
            'amount.required'              => 'Indica el importe.',
            'amount.min'                   => 'El importe tiene que ser mayor que cero.',
            'movement_date.required'       => 'Indica la fecha.',
            'movement_date.before_or_equal' => 'Un gasto no puede tener fecha futura.',
        ]);

        [$sessionId, $accountId] = $this->accounts->sourceFromRequest($request, $companyId);

        return $this->accounts->spend($companyId, $sessionId, $accountId, [
            'expense_category_id' => $category->id,
            'category'       => $category->name,
            'concept'        => trim($data['concept']),
            'amount'         => $data['amount'],
            'movement_date'  => $data['movement_date'],
            'reference'      => $data['reference'] ?? null,
            'notes'          => $data['notes'] ?? null,
            // Solo se guarda el destinatario que pide la categoría.
            'supplier_id'    => $category->payee === 'proveedor' ? ($data['supplier_id'] ?? null) : null,
            'personal_id'    => $category->payee === 'personal' ? ($data['personal_id'] ?? null) : null,
            'branch_id'      => $this->branches->resolve($companyId, $data['branch_id'] ?? null, $sessionId, null, auth()->id()),
            'payment_method' => $this->method($companyId, $sessionId),
        ]);
    }

    /**
     * Anula el gasto: el dinero vuelve a la cuenta o a un turno abierto. El
     * gasto no se borra: queda marcado, con su motivo.
     *
     * @throws DomainException
     */
    public function cancel(CashMovement $expense, ?string $reason): void
    {
        if ($expense->type !== 'egreso') {
            throw new DomainException('Solo se anulan gastos.');
        }
        if ($expense->isCancelled()) {
            throw new DomainException('Este gasto ya está anulado.');
        }
        if ($expense->isGenerated()) {
            throw new DomainException('Este gasto proviene de otro módulo (pago, compra o liquidación): anúlalo desde su origen.');
        }

        DB::transaction(function () use ($expense, $reason) {
            $this->accounts->refundExpense($expense);
            $expense->update(['cancelled_at' => now(), 'cancelled_by' => auth()->id(), 'cancel_reason' => $reason ?: null]);
        });
    }

    /** «Pago a X», «LUZ · noviembre 2026», «HOSTING · 2026»; un gasto general se describe a mano. */
    protected function defaultDescription(ExpenseCategory $category, Request $request): ?string
    {
        $on = rescue(fn () => \Illuminate\Support\Carbon::parse((string) $request->input('movement_date')), now(), false);

        return match ($category->group()) {
            'basicos'   => $category->name . ' · ' . ($category->isAnnual() ? $on->year : BasicServicesMonth::MONTHS[$on->month - 1] . ' ' . $on->year),
            'proveedor' => ($name = Supplier::forCompany($category->company_id)->whereKey($request->input('supplier_id'))->value('name')) ? "Pago a {$name}" : $category->name,
            'personal'  => ($name = Personal::forCompany($category->company_id)->whereKey($request->input('personal_id'))->value('full_name')) ? "Pago a {$name}" : $category->name,
            default     => null,
        };
    }

    /** Solo el efectivo está en el cajón; desde una cuenta, transferencia si el salón la usa. */
    protected function method(int $companyId, ?int $sessionId): string
    {
        if ($sessionId) {
            return 'efectivo';
        }
        $options = PaymentMethod::optionsFor($companyId);

        return isset($options['transferencia']) ? 'transferencia' : PaymentMethod::defaultCodeFor($companyId);
    }
}
