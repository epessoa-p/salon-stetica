<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\Caja;
use App\Models\CashMovement;
use App\Models\CashSession;
use App\Models\PaymentMethod;
use Illuminate\Http\Request;

class CashSessionController extends Controller
{
    /** Listado de sesiones (arqueos) de la empresa. */
    public function index()
    {
        $companyId = $this->companyId();
        if (!$companyId) {
            return view('finance.cash_sessions.index', ['sessions' => null, 'noCompany' => true]);
        }

        $sessions = CashSession::with(['register', 'movements'])
            ->whereHas('register', fn ($q) => $q->where('company_id', $companyId))
            ->latest('opened_at')
            ->paginate(15);

        return view('finance.cash_sessions.index', ['sessions' => $sessions, 'noCompany' => false]);
    }

    /** Formulario de apertura de caja. */
    public function create()
    {
        $companyId = $this->companyId();
        if (!$companyId) {
            return redirect()->route('cash-sessions.index');
        }

        return view('finance.cash_sessions.create', [
            'registers' => Caja::where('company_id', $companyId)->where('active', true)->orderBy('name')->get(),
            'openRegisterIds' => $this->openRegisterIds($companyId),
        ]);
    }

    /** Abre una sesión de caja. */
    public function store(Request $request)
    {
        $companyId = $this->companyId();
        if (!$companyId) {
            return back()->withErrors(['error' => 'Debes operar dentro de una empresa.']);
        }

        $validated = $request->validate([
            'cash_register_id' => ['required', 'exists:cash_registers,id'],
            'opening_amount'   => ['required', 'numeric', 'min:0'],
            'notes'            => ['nullable', 'string'],
        ]);

        $register = Caja::where('id', $validated['cash_register_id'])->where('company_id', $companyId)->firstOrFail();

        // No permitir dos sesiones abiertas en la misma caja.
        if (CashSession::where('cash_register_id', $register->id)->where('status', 'open')->exists()) {
            return back()->withInput()->withErrors(['cash_register_id' => 'Esta caja ya tiene una sesión abierta.']);
        }

        $session = CashSession::create([
            'cash_register_id' => $register->id,
            'personal_id'      => auth()->user()->personal?->id,
            'opening_amount'   => $validated['opening_amount'],
            'status'           => 'open',
            'opened_at'        => now(),
            'notes'            => $validated['notes'] ?? null,
            'opened_by'        => auth()->id(),
        ]);

        return redirect()->route('cash-sessions.show', $session)->with('success', 'Caja abierta correctamente.');
    }

    /** Detalle de la sesión: movimientos, saldo esperado y arqueo. */
    public function show(CashSession $cashSession)
    {
        $this->authorizeSession($cashSession);
        $cashSession->load(['register', 'movements' => fn ($q) => $q->latest('id'), 'sales']);

        return view('finance.cash_sessions.show', ['session' => $cashSession]);
    }

    /** Registra un movimiento manual (ingreso/egreso). */
    public function addMovement(Request $request, CashSession $cashSession)
    {
        $this->authorizeSession($cashSession);

        if (!$cashSession->isOpen()) {
            return back()->withErrors(['error' => 'La caja está cerrada.']);
        }

        $validated = $request->validate([
            'type'           => ['required', 'in:ingreso,egreso'],
            'amount'         => ['required', 'numeric', 'min:0.01'],
            'concept'        => ['required', 'string', 'max:255'],
            'payment_method' => ['nullable', 'string', 'max:30', 'in:' . implode(',', array_keys(PaymentMethod::optionsFor()))],
        ]);

        $cashSession->movements()->create([
            'type'           => $validated['type'],
            'amount'         => $validated['amount'],
            'concept'        => $validated['concept'],
            'payment_method' => $validated['payment_method'] ?? PaymentMethod::defaultCodeFor(),
            'created_by'     => auth()->id(),
        ]);

        return back()->with('success', 'Movimiento registrado.');
    }

    /** Cierra la caja (arqueo): compara lo contado con lo esperado. */
    public function close(Request $request, CashSession $cashSession)
    {
        $this->authorizeSession($cashSession);

        if (!$cashSession->isOpen()) {
            return back()->withErrors(['error' => 'La caja ya está cerrada.']);
        }

        $validated = $request->validate([
            'closing_amount' => ['required', 'numeric', 'min:0'],
            'notes'          => ['nullable', 'string'],
        ]);

        $expected = $cashSession->expectedBalance();

        $cashSession->update([
            'closing_amount'  => $validated['closing_amount'],
            'expected_amount' => $expected,
            'difference'      => (float) $validated['closing_amount'] - $expected,
            'status'          => 'closed',
            'closed_at'       => now(),
            'closed_by'       => auth()->id(),
            'notes'           => $validated['notes'] ?? $cashSession->notes,
        ]);

        return redirect()->route('cash-sessions.show', $cashSession)->with('success', 'Caja cerrada. Arqueo registrado.');
    }

    // ---- Helpers ----

    protected function companyId(): ?int
    {
        return auth()->user()->getCurrentCompany()?->id;
    }

    protected function openRegisterIds(int $companyId): array
    {
        return CashSession::where('status', 'open')
            ->whereHas('register', fn ($q) => $q->where('company_id', $companyId))
            ->pluck('cash_register_id')
            ->all();
    }

    protected function authorizeSession(CashSession $session): void
    {
        $companyId = $this->companyId();
        if (!$session->register || $session->register->company_id !== $companyId) {
            abort(403);
        }
    }
}
