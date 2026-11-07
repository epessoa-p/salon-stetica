<?php

namespace App\Services;

use App\Models\AccountMovement;
use App\Models\CashMovement;
use App\Models\CashSession;
use App\Models\Company;
use App\Models\FinancialAccount;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Cuentas con movimientos (caja chica, bancos…) y origen de los pagos.
 *
 * Reglas de Glamora:
 *  - El saldo es la suma de los movimientos no anulados y nunca queda negativo.
 *  - Todo pago sale por spend(): de un turno de caja abierto o de una cuenta.
 *    cash_movements sigue siendo el registro de gastos e ingresos del negocio;
 *    account_movements es el libro de la cuenta, que además tiene aportes,
 *    traspasos y ajustes que no son gastos.
 *  - Un traspaso con una caja deja un cash_movement para que el arqueo cuadre,
 *    con categoría de traspaso: no cuenta como gasto ni como ingreso.
 *  - Anular no borra: marca el movimiento y deja de contar en el saldo.
 */
class AccountService
{
    // ---- Origen de un pago ----

    /** Lo que el usuario puede elegir como origen de un pago. */
    public function sourceOptions(int $companyId): array
    {
        $canUse = $this->canUseAccounts($companyId);

        return [
            'sessions' => CashSession::with('register')->where('status', 'open')
                ->whereHas('register', fn ($q) => $q->withoutGlobalScopes()->where('company_id', $companyId))->get(),
            'accounts' => $canUse
                ? FinancialAccount::forCompany($companyId)->where('active', true)->select('financial_accounts.*')->withBalance()->orderBy('name')->get()
                : collect(),
            'canUseAccounts' => $canUse,
        ];
    }

    /**
     * Lee el origen del formulario y lo valida. Devuelve [sessionId, accountId].
     *
     * @throws DomainException
     */
    public function sourceFromRequest(Request $request, int $companyId, bool $required = true): array
    {
        $sessionId = $request->integer('cash_session_id') ?: null;
        $accountId = $request->integer('financial_account_id') ?: null;
        $type = $request->input('source_type') ?? ($sessionId ? 'caja' : ($accountId ? 'cuenta' : null));

        if ($type === 'caja') {
            if (!$sessionId) {
                throw new DomainException('Elige la caja abierta.');
            }
            $this->openSession($companyId, $sessionId);

            return [$sessionId, null];
        }

        if ($type === 'cuenta') {
            if (!$accountId) {
                throw new DomainException('Elige la cuenta.');
            }
            if (!$this->canUseAccounts($companyId)) {
                throw new DomainException('No tienes permiso para usar las cuentas.');
            }
            $this->account($companyId, $accountId);

            return [null, $accountId];
        }

        if ($required) {
            throw new DomainException('Elige de dónde sale el dinero: una caja abierta o una cuenta.');
        }

        return [null, null];
    }

    protected function canUseAccounts(int $companyId): bool
    {
        $user = auth()->user();
        $company = Company::find($companyId);

        return $company && $company->planAllows('finance') && $user && $user->hasPermissionInCompany('accounts.view', $company);
    }

    // ---- Salidas y entradas ----

    /**
     * Punto único de salida de dinero. $data: amount, concept, category,
     * expense_category_id, movement_date, payment_method, reference, notes,
     * branch_id, supplier_id, personal_id, created_by.
     *
     * @throws DomainException
     */
    public function spend(int $companyId, ?int $sessionId, ?int $accountId, array $data): CashMovement
    {
        return DB::transaction(function () use ($companyId, $sessionId, $accountId, $data) {
            $amount = round((float) $data['amount'], 2);
            $session = $sessionId ? $this->openSession($companyId, $sessionId) : null;
            $account = null;
            if ($accountId) {
                $account = $this->lockedAccount($companyId, $accountId);
                $this->assertCovers($account, $amount);
            }

            $movement = $this->cashMovement($companyId, $session?->id, $account?->id, 'egreso', $amount, $data + ['number' => $this->nextExpenseNumber($companyId)]);
            if ($account) {
                $this->entry($account, 'gasto', 'out', $amount, $data, $movement->id);
            }

            return $movement;
        });
    }

    /** Dinero que entra al negocio (ingreso, devolución de un proveedor). @throws DomainException */
    public function receive(int $companyId, ?int $sessionId, ?int $accountId, array $data): CashMovement
    {
        return DB::transaction(function () use ($companyId, $sessionId, $accountId, $data) {
            $amount = round((float) $data['amount'], 2);
            $session = $sessionId ? $this->openSession($companyId, $sessionId) : null;
            $account = $accountId ? $this->lockedAccount($companyId, $accountId) : null;

            $movement = $this->cashMovement($companyId, $session?->id, $account?->id, 'ingreso', $amount, $data);
            if ($account) {
                $this->entry($account, 'ingreso', 'in', $amount, $data, $movement->id);
            }

            return $movement;
        });
    }

    // ---- Movimientos propios de la cuenta ----

    /** Dinero que entra de fuera (dueño, socio): no es un ingreso del negocio. */
    public function contribute(FinancialAccount $account, float $amount, array $data = [], string $type = 'manual'): AccountMovement
    {
        return DB::transaction(function () use ($account, $amount, $data, $type) {
            $locked = $this->lockedAccount((int) $account->company_id, $account->id);

            return $this->entry($locked, $type, 'in', round($amount, 2), $data + ['concept' => 'Ingreso']);
        });
    }

    /** Dinero que sale sin ser un gasto del negocio. @throws DomainException */
    public function withdraw(FinancialAccount $account, float $amount, array $data = []): AccountMovement
    {
        return DB::transaction(function () use ($account, $amount, $data) {
            $locked = $this->lockedAccount((int) $account->company_id, $account->id);
            $amount = round($amount, 2);
            $this->assertCovers($locked, $amount);

            return $this->entry($locked, 'manual', 'out', $amount, $data + ['concept' => 'Egreso']);
        });
    }

    /**
     * Aporta a la cuenta según el origen del formulario: caja o cuenta son un
     * traspaso; sin origen es un aporte externo.
     *
     * @throws DomainException
     */
    public function fundFromRequest(Request $request, FinancialAccount $account, float $amount, string $concept, string $date, ?string $reference = null): void
    {
        $companyId = (int) $account->company_id;
        [$sessionId, $accountId] = $this->sourceFromRequest($request, $companyId, false);
        $data = ['concept' => $concept, 'movement_date' => $date, 'reference' => $reference];

        if ($sessionId) {
            $this->transfer($companyId, ['caja', $sessionId], ['cuenta', $account->id], $amount, $data);
        } elseif ($accountId) {
            $this->transfer($companyId, ['cuenta', $accountId], ['cuenta', $account->id], $amount, $data);
        } else {
            $this->contribute($account, $amount, $data);
        }
    }

    /**
     * Traspaso de dinero. $from y $to: ['caja', id] o ['cuenta', id].
     *
     * @throws DomainException
     */
    public function transfer(int $companyId, array $from, array $to, float $amount, array $data = []): void
    {
        [$fromType, $fromId] = $from;
        [$toType, $toId] = $to;
        $amount = round($amount, 2);

        if ($fromType === $toType && (int) $fromId === (int) $toId) {
            throw new DomainException('El origen y el destino deben ser distintos.');
        }
        if ($fromType === 'caja' && $toType === 'caja') {
            throw new DomainException('Un traspaso debe incluir al menos una cuenta.');
        }

        DB::transaction(function () use ($companyId, $fromType, $fromId, $toType, $toId, $amount, $data) {
            // Siempre en el mismo orden, para que dos traspasos cruzados no se bloqueen.
            $ids = collect([$fromType === 'cuenta' ? $fromId : null, $toType === 'cuenta' ? $toId : null])->filter()->map(fn ($i) => (int) $i)->sort();
            $locked = [];
            foreach ($ids as $id) {
                $locked[$id] = $this->lockedAccount($companyId, $id);
            }

            $source = $fromType === 'cuenta' ? $locked[(int) $fromId] : $this->openSession($companyId, (int) $fromId);
            $target = $toType === 'cuenta' ? $locked[(int) $toId] : $this->openSession($companyId, (int) $toId);

            if ($source instanceof FinancialAccount) {
                $this->assertCovers($source, $amount);
            } elseif ($source->expectedBalance() + 0.001 < $amount) {
                throw new DomainException('La caja solo tiene ' . number_format($source->expectedBalance(), 2) . ' disponibles.');
            }

            $data += ['concept' => 'Traspaso de ' . $this->label($source) . ' a ' . $this->label($target)];
            $data['category'] = CashMovement::CATEGORY_TRANSFER;

            $cashMovementId = null;
            if ($source instanceof CashSession) {
                $cashMovementId = $this->cashMovement($companyId, $source->id, $target->id, 'egreso', $amount, $data)->id;
            } elseif ($target instanceof CashSession) {
                $cashMovementId = $this->cashMovement($companyId, $target->id, $source->id, 'ingreso', $amount, $data)->id;
            }

            $out = $source instanceof FinancialAccount ? $this->entry($source, 'traspaso_salida', 'out', $amount, $data, $cashMovementId) : null;
            $in = $target instanceof FinancialAccount ? $this->entry($target, 'traspaso_entrada', 'in', $amount, $data, $cashMovementId) : null;

            if ($out && $in) {
                $out->update(['counterpart_movement_id' => $in->id]);
                $in->update(['counterpart_movement_id' => $out->id]);
            }
        });
    }

    /**
     * Anula un movimiento de la cuenta (y su otra pata si es un traspaso).
     *
     * @throws DomainException
     */
    public function void(AccountMovement $movement): void
    {
        DB::transaction(function () use ($movement) {
            if ($movement->isVoided()) {
                throw new DomainException('Este movimiento ya está anulado.');
            }

            if (!$movement->canVoidHere()) {
                throw new DomainException('Este movimiento se anula desde su origen: el gasto, el pago o la compra.');
            }

            $cash = $movement->cash_movement_id ? CashMovement::withoutGlobalScopes()->find($movement->cash_movement_id) : null;
            if ($cash?->cash_session_id && !CashSession::find($cash->cash_session_id)?->isOpen()) {
                throw new DomainException('La caja de este traspaso ya se cerró: no se puede anular.');
            }

            $rows = collect([$movement]);
            if ($movement->counterpart_movement_id) {
                $rows->push(AccountMovement::withoutGlobalScopes()->find($movement->counterpart_movement_id));
            }

            foreach ($rows->filter()->sortBy('financial_account_id') as $row) {
                $account = $this->lockedAccount((int) $row->company_id, (int) $row->financial_account_id, false);
                if ($row->isIn() && $account->balance() + 0.001 < (float) $row->amount) {
                    throw new DomainException("No se puede anular: el dinero ya se usó y la cuenta «{$account->name}» quedaría en negativo.");
                }
                $this->markVoided($row);
            }

            $cash?->delete();
        });
    }

    /**
     * Anula el dinero de un pago desde su módulo de origen: borra el
     * cash_movement y devuelve el saldo a la cuenta si salió de una.
     */
    public function voidCashMovement(?int $cashMovementId): void
    {
        if (!$cashMovementId) {
            return;
        }

        DB::transaction(function () use ($cashMovementId) {
            AccountMovement::withoutGlobalScopes()->where('cash_movement_id', $cashMovementId)->whereNull('voided_at')
                ->get()->each(fn ($row) => $this->markVoided($row));
            CashMovement::withoutGlobalScopes()->find($cashMovementId)?->delete();
        });
    }

    /**
     * Devuelve el dinero de un gasto anulado. De una cuenta: se anula su línea.
     * De la caja: ingreso en el mismo turno si sigue abierto o, si no, en otro
     * abierto. El arqueo de un turno cerrado no se altera.
     *
     * @throws DomainException
     */
    public function refundExpense(CashMovement $expense): void
    {
        DB::transaction(function () use ($expense) {
            if ($expense->financial_account_id) {
                AccountMovement::withoutGlobalScopes()->where('cash_movement_id', $expense->id)->whereNull('voided_at')
                    ->get()->each(fn ($row) => $this->markVoided($row));

                return;
            }

            if (!$expense->cash_session_id) {
                return; // Gasto antiguo sin origen: no hay dinero que devolver.
            }

            $companyId = (int) $expense->company_id;
            $open = fn () => CashSession::where('status', 'open')->whereHas('register', fn ($q) => $q->withoutGlobalScopes()->where('company_id', $companyId));
            $session = (clone $open())->whereKey($expense->cash_session_id)->first() ?? $open()->first();
            if (!$session) {
                throw new DomainException('La caja de este gasto ya se cerró: abre un turno para devolver ahí el dinero.');
            }

            $this->cashMovement($companyId, $session->id, null, 'ingreso', (float) $expense->amount, [
                'concept'       => 'Anulación gasto ' . ($expense->number ?: $expense->concept),
                'category'      => CashMovement::CATEGORY_TRANSFER,
                'movement_date' => today(),
                'reference'     => $expense->number,
            ]);
        });
    }

    /** Siguiente número de gasto de la empresa (G-000001). */
    public function nextExpenseNumber(int $companyId): string
    {
        $last = CashMovement::withoutGlobalScopes()->withTrashed()->where('company_id', $companyId)
            ->where('number', 'like', 'G-%')->lockForUpdate()
            ->selectRaw('MAX(CAST(SUBSTRING(number, 3) AS UNSIGNED)) as n')->value('n');

        return 'G-' . str_pad((string) ((int) $last + 1), 6, '0', STR_PAD_LEFT);
    }

    // ---- Internos ----

    protected function markVoided(AccountMovement $row): void
    {
        $row->update(['voided_at' => now(), 'voided_by' => auth()->id()]);
    }

    /** @throws DomainException */
    protected function openSession(int $companyId, int $sessionId): CashSession
    {
        $session = CashSession::where('id', $sessionId)->where('status', 'open')
            ->whereHas('register', fn ($q) => $q->withoutGlobalScopes()->where('company_id', $companyId))
            ->with('register')->first();

        return $session ?? throw new DomainException('La caja elegida no está abierta.');
    }

    /** @throws DomainException */
    protected function account(int $companyId, int $accountId): FinancialAccount
    {
        $account = FinancialAccount::forCompany($companyId)->whereKey($accountId)->where('active', true)->first();

        return $account ?? throw new DomainException('La cuenta elegida no está disponible.');
    }

    /** @throws DomainException */
    protected function lockedAccount(int $companyId, int $accountId, bool $mustBeActive = true): FinancialAccount
    {
        $account = FinancialAccount::forCompany($companyId)->whereKey($accountId)
            ->when($mustBeActive, fn ($q) => $q->where('active', true))
            ->lockForUpdate()->first();

        return $account ?? throw new DomainException('La cuenta elegida no está disponible.');
    }

    /** @throws DomainException */
    protected function assertCovers(FinancialAccount $account, float $amount): void
    {
        $balance = $account->balance();
        if ($balance + 0.001 < $amount) {
            throw new DomainException("Saldo insuficiente en «{$account->name}»: tiene " . number_format($balance, 2) . ' y el pago es de ' . number_format($amount, 2) . '.');
        }
    }

    protected function label(CashSession|FinancialAccount $place): string
    {
        return $place instanceof CashSession ? ($place->register?->name ?? 'Caja') : $place->name;
    }

    protected function cashMovement(int $companyId, ?int $sessionId, ?int $accountId, string $type, float $amount, array $data): CashMovement
    {
        return CashMovement::create([
            'company_id'           => $companyId,
            'cash_session_id'      => $sessionId,
            'financial_account_id' => $accountId,
            'branch_id'            => $data['branch_id'] ?? null,
            'number'               => $data['number'] ?? null,
            'expense_category_id'  => $data['expense_category_id'] ?? null,
            'notes'                => $data['notes'] ?? null,
            'type'                 => $type,
            'amount'               => $amount,
            'concept'              => $data['concept'],
            'category'             => $data['category'] ?? null,
            'movement_date'        => $data['movement_date'] ?? today(),
            'payment_method'       => $data['payment_method'] ?? 'efectivo',
            'reference'            => $data['reference'] ?? null,
            'supplier_id'          => $data['supplier_id'] ?? null,
            'personal_id'          => $data['personal_id'] ?? null,
            'created_by'           => $data['created_by'] ?? auth()->id(),
        ]);
    }

    protected function entry(FinancialAccount $account, string $type, string $direction, float $amount, array $data, ?int $cashMovementId = null): AccountMovement
    {
        return AccountMovement::create([
            'company_id'           => $account->company_id,
            'financial_account_id' => $account->id,
            'type'                 => $type,
            'direction'            => $direction,
            'amount'               => $amount,
            'concept'              => $data['concept'],
            'category'             => $data['category'] ?? null,
            'movement_date'        => $data['movement_date'] ?? today(),
            'reference'            => $data['reference'] ?? null,
            'cash_movement_id'     => $cashMovementId,
            'created_by'           => $data['created_by'] ?? auth()->id(),
        ]);
    }
}
