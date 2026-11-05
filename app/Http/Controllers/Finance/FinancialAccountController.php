<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\AccountMovement;
use App\Models\CompanySetting;
use App\Models\FinancialAccount;
use App\Services\AccountService;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/** Cuentas de la empresa (caja chica, bancos…) y sus movimientos. */
class FinancialAccountController extends Controller
{
    public function __construct(protected AccountService $accounts)
    {
    }

    public function index()
    {
        $companyId = $this->companyId();
        if (!$companyId) {
            return view('finance.accounts.index', ['noCompany' => true]);
        }

        $list = FinancialAccount::forCompany($companyId)->select('financial_accounts.*')->withBalance()
            ->orderByDesc('active')->orderBy('name')->get();

        return view('finance.accounts.index', [
            'noCompany' => false,
            'accounts'  => $list,
            'total'     => (float) $list->where('active', true)->sum('saldo'),
            'lastMoves' => AccountMovement::where('company_id', $companyId)->whereNull('voided_at')
                ->orderByDesc('movement_date')->orderByDesc('id')->get()->unique('financial_account_id')->keyBy('financial_account_id'),
            'setting'   => CompanySetting::current($companyId),
        ]);
    }

    public function create()
    {
        $companyId = $this->companyId();
        if (!$companyId) {
            return redirect()->route('accounts.index');
        }

        return view('finance.accounts.create', [
            'account' => new FinancialAccount(['type' => 'caja_chica', 'active' => true]),
            'types'   => FinancialAccount::TYPES,
            'sources' => $this->accounts->sourceOptions($companyId),
            'setting' => CompanySetting::current($companyId),
        ]);
    }

    public function store(Request $request)
    {
        $companyId = $this->companyId();
        if (!$companyId) {
            return back()->withErrors(['error' => 'Debes operar dentro de una empresa.']);
        }

        $data = $this->validated($request, $companyId);
        $request->validate(['initial_amount' => ['nullable', 'numeric', 'min:0']], ['initial_amount.numeric' => 'El aporte inicial debe ser un monto.']);
        $initial = round((float) $request->input('initial_amount'), 2);

        try {
            $account = DB::transaction(function () use ($request, $data, $companyId, $initial) {
                $account = FinancialAccount::create($data + ['company_id' => $companyId, 'created_by' => auth()->id()]);
                if ($initial > 0) {
                    $this->fund($request, $account, $initial, 'Aporte inicial', today()->toDateString());
                }

                return $account;
            });
        } catch (DomainException $e) {
            return back()->withInput()->withErrors(['error' => $e->getMessage()]);
        }

        return redirect()->route('accounts.show', $account)->with('success', 'Cuenta «' . $account->name . '» creada.');
    }

    public function show(Request $request, FinancialAccount $financialAccount)
    {
        $this->authorizeAccount($financialAccount);
        $from = $request->input('from');
        $to = $request->input('to');

        $movements = $financialAccount->movements()->with('creator')
            ->when($from, fn ($q) => $q->where('movement_date', '>=', $from))
            ->when($to, fn ($q) => $q->where('movement_date', '<=', $to))
            ->orderByDesc('movement_date')->orderByDesc('id')
            ->paginate(30)->withQueryString();

        // Saldo tras cada línea: se parte de la más reciente de la página y se baja.
        $running = null;
        $first = $movements->first();
        if ($first) {
            $running = (float) $financialAccount->movements()->whereNull('voided_at')
                ->where(fn ($q) => $q->where('movement_date', '<', $first->movement_date)
                    ->orWhere(fn ($s) => $s->whereDate('movement_date', $first->movement_date)->where('id', '<=', $first->id)))
                ->selectRaw("COALESCE(SUM(CASE WHEN direction = 'in' THEN amount ELSE -amount END), 0) as saldo")->value('saldo');
        }

        $companyId = (int) $financialAccount->company_id;
        $sources = $this->accounts->sourceOptions($companyId);

        return view('finance.accounts.show', [
            'account'   => $financialAccount,
            'balance'   => $financialAccount->balance(),
            'movements' => $movements,
            'running'   => $running,
            'filters'   => compact('from', 'to'),
            'sources'   => $sources,
            'others'    => $sources['accounts']->where('id', '!=', $financialAccount->id)->values(),
            'setting'   => CompanySetting::current($companyId),
        ]);
    }

    public function edit(FinancialAccount $financialAccount)
    {
        $this->authorizeAccount($financialAccount);

        return view('finance.accounts.edit', ['account' => $financialAccount, 'types' => FinancialAccount::TYPES]);
    }

    public function update(Request $request, FinancialAccount $financialAccount)
    {
        $this->authorizeAccount($financialAccount);
        $data = $this->validated($request, (int) $financialAccount->company_id, $financialAccount->id);
        $data['active'] = $request->boolean('active');

        if (!$data['active'] && abs($financialAccount->balance()) > 0.001) {
            return back()->withInput()->withErrors(['error' => 'La cuenta tiene saldo de ' . number_format($financialAccount->balance(), 2) . ': trasládalo antes de desactivarla.']);
        }

        $financialAccount->update($data);

        return redirect()->route('accounts.show', $financialAccount)->with('success', 'Cuenta actualizada.');
    }

    public function destroy(FinancialAccount $financialAccount)
    {
        $this->authorizeAccount($financialAccount);

        if ($financialAccount->movements()->exists()) {
            return back()->withErrors(['error' => 'La cuenta tiene movimientos: desactívala en lugar de eliminarla.']);
        }

        $financialAccount->delete();

        return redirect()->route('accounts.index')->with('success', 'Cuenta eliminada.');
    }

    /** Aporte: externo o desde una caja abierta u otra cuenta. */
    public function contribute(Request $request, FinancialAccount $financialAccount)
    {
        $this->authorizeAccount($financialAccount);
        $data = $this->moneyData($request);

        try {
            $this->fund($request, $financialAccount, (float) $data['amount'], $data['concept'] ?: 'Aporte', $data['movement_date']);
        } catch (DomainException $e) {
            return back()->withInput()->withErrors(['error' => $e->getMessage()]);
        }

        return back()->with('success', 'Aporte registrado.');
    }

    /** Traspaso hacia otra cuenta o hacia una caja abierta. Destino: cuenta:ID o caja:ID. */
    public function transfer(Request $request, FinancialAccount $financialAccount)
    {
        $this->authorizeAccount($financialAccount);
        $data = $this->moneyData($request);
        $request->validate(['destination' => ['required', 'regex:/^(cuenta|caja):\d+$/']], ['destination.required' => 'Elige el destino.', 'destination.regex' => 'Elige el destino.']);
        [$type, $id] = explode(':', $request->input('destination'));

        try {
            $this->accounts->transfer((int) $financialAccount->company_id, ['cuenta', $financialAccount->id], [$type, (int) $id], (float) $data['amount'], array_filter([
                'concept' => $data['concept'], 'movement_date' => $data['movement_date'],
            ]));
        } catch (DomainException $e) {
            return back()->withInput()->withErrors(['error' => $e->getMessage()]);
        }

        return back()->with('success', 'Traspaso registrado.');
    }

    public function adjust(Request $request, FinancialAccount $financialAccount)
    {
        $this->authorizeAccount($financialAccount);
        $data = $request->validate([
            'direction' => ['required', 'in:in,out'],
            'amount'    => ['required', 'numeric', 'min:0.01'],
            'reason'    => ['required', 'string', 'max:200'],
        ], ['reason.required' => 'Indica el motivo del ajuste.', 'amount.min' => 'El monto debe ser mayor a 0.']);

        try {
            $this->accounts->adjust($financialAccount, $data['direction'], (float) $data['amount'], $data['reason']);
        } catch (DomainException $e) {
            return back()->withInput()->withErrors(['error' => $e->getMessage()]);
        }

        return back()->with('success', 'Ajuste registrado.');
    }

    public function voidMovement(FinancialAccount $financialAccount, AccountMovement $movement)
    {
        $this->authorizeAccount($financialAccount);
        abort_if((int) $movement->financial_account_id !== $financialAccount->id, 404);

        try {
            $this->accounts->void($movement);
        } catch (DomainException $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }

        return back()->with('success', 'Movimiento anulado.');
    }

    // ---- Helpers ----

    /** Aporta a la cuenta según el origen elegido (externo si no hay caja ni cuenta). @throws DomainException */
    protected function fund(Request $request, FinancialAccount $account, float $amount, string $concept, string $date): void
    {
        $companyId = (int) $account->company_id;
        [$sessionId, $accountId] = $this->accounts->sourceFromRequest($request, $companyId, false);
        $data = ['concept' => $concept, 'movement_date' => $date];

        if ($sessionId) {
            $this->accounts->transfer($companyId, ['caja', $sessionId], ['cuenta', $account->id], $amount, $data);
        } elseif ($accountId) {
            $this->accounts->transfer($companyId, ['cuenta', $accountId], ['cuenta', $account->id], $amount, $data);
        } else {
            $this->accounts->contribute($account, $amount, $data);
        }
    }

    protected function moneyData(Request $request): array
    {
        return $request->validate([
            'amount'        => ['required', 'numeric', 'min:0.01'],
            'concept'       => ['nullable', 'string', 'max:255'],
            'movement_date' => ['required', 'date', 'before_or_equal:today'],
        ], [
            'amount.required' => 'Indica el monto.',
            'amount.min'      => 'El monto debe ser mayor a 0.',
            'movement_date.before_or_equal' => 'La fecha no puede ser futura.',
        ]) + ['concept' => null];
    }

    protected function validated(Request $request, int $companyId, ?int $ignoreId = null): array
    {
        $data = $request->validate([
            'name'           => ['required', 'string', 'max:120', Rule::unique('financial_accounts', 'name')->where('company_id', $companyId)->whereNull('deleted_at')->ignore($ignoreId)],
            'type'           => ['required', Rule::in(array_keys(FinancialAccount::TYPES))],
            'bank_name'      => ['nullable', 'string', 'max:120'],
            'account_number' => ['nullable', 'string', 'max:60'],
            'notes'          => ['nullable', 'string', 'max:255'],
        ], ['name.required' => 'Escribe el nombre de la cuenta.', 'name.unique' => 'Ya existe una cuenta con ese nombre.']);

        // Banco y número solo aplican a los bancos.
        if ($data['type'] !== 'banco') {
            $data['bank_name'] = null;
            $data['account_number'] = null;
        }

        return $data;
    }

    protected function authorizeAccount(FinancialAccount $account): void
    {
        abort_if((int) $account->company_id !== (int) $this->companyId(), 403);
    }

    protected function companyId(): ?int
    {
        return auth()->user()->getCurrentCompany()?->id;
    }
}
