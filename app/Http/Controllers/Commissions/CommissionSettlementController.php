<?php

namespace App\Http\Controllers\Commissions;

use App\Http\Controllers\Controller;
use App\Models\CommissionSettlement;
use App\Models\Personal;
use App\Services\CommissionService;
use DomainException;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class CommissionSettlementController extends Controller
{
    public function __construct(protected CommissionService $service)
    {
    }

    /** Historial de liquidaciones, de la más reciente a la más antigua. */
    public function index(Request $request)
    {
        $companyId = $this->companyId();
        if (!$companyId) {
            return view('commissions.settlements.index', ['noCompany' => true]);
        }

        $personalId = $request->integer('personal_id') ?: null;

        $settlements = CommissionSettlement::with(['specialist', 'payer'])
            ->where('company_id', $companyId)
            ->when($personalId, fn ($q) => $q->where('personal_id', $personalId))
            ->orderByDesc('paid_at')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('commissions.settlements.index', [
            'noCompany'   => false,
            'settlements' => $settlements,
            'specialists' => Personal::where('company_id', $companyId)->orderBy('full_name')->get(),
            'personalId'  => $personalId,
        ]);
    }

    /** Comprobante de la liquidación, imprimible para que firme el profesional. */
    public function show(CommissionSettlement $settlement)
    {
        $this->authorizeSettlement($settlement);

        $settlement->load([
            'specialist', 'payer', 'session.register',
            'commissions' => fn ($q) => $q->orderBy('accrued_at'),
            'commissions.sale', 'commissions.saleItem.service', 'commissions.saleItem.product',
            'tipSales' => fn ($q) => $q->orderBy('sale_date'),
        ]);

        return view('commissions.settlements.show', ['settlement' => $settlement]);
    }

    /** Liquida (paga) las comisiones pendientes de un profesional en un periodo. */
    public function store(Request $request)
    {
        $companyId = $this->companyId();
        if (!$companyId) {
            return back()->withErrors(['error' => 'Debes operar dentro de una empresa.']);
        }

        $validated = $request->validate([
            'personal_id'     => ['required', 'integer'],
            'from'            => ['required', 'date'],
            'to'              => ['required', 'date', 'after_or_equal:from'],
            'include_tips'    => ['nullable', 'boolean'],
            'cash_session_id' => ['nullable', 'integer'],
            'notes'           => ['nullable', 'string', 'max:1000'],
        ], [
            'to.after_or_equal' => 'La fecha fin no puede ser anterior a la de inicio.',
        ]);

        $personal = Personal::where('company_id', $companyId)->findOrFail($validated['personal_id']);

        try {
            $settlement = $this->service->settle(
                $personal,
                Carbon::parse($validated['from']),
                Carbon::parse($validated['to']),
                $request->boolean('include_tips'),
                $validated['cash_session_id'] ?? null,
                $validated['notes'] ?? null,
                auth()->user(),
            );
        } catch (DomainException $e) {
            return back()->withInput()->withErrors(['error' => $e->getMessage()]);
        } catch (\Throwable $e) {
            Log::error('Error al liquidar comisiones', ['personal_id' => $personal->id, 'message' => $e->getMessage()]);

            return back()->withInput()->withErrors(['error' => 'No fue posible registrar la liquidación.']);
        }

        return redirect()
            ->route('commission-settlements.show', $settlement)
            ->with('success', "Liquidación {$settlement->number} registrada por " . number_format((float) $settlement->total, 2) . '.');
    }

    protected function authorizeSettlement(CommissionSettlement $settlement): void
    {
        if ($settlement->company_id !== $this->companyId()) {
            abort(403);
        }
    }

    protected function companyId(): ?int
    {
        return auth()->user()->getCurrentCompany()?->id;
    }
}
