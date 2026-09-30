<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Commission;
use App\Models\Personal;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class CommissionController extends Controller
{
    public function index(Request $request)
    {
        $companyId = auth()->user()->getCurrentCompany()?->id;

        if (!$companyId) {
            return view('staff.commissions.index', ['commissions' => null, 'noCompany' => true]);
        }

        $from = Carbon::parse($request->input('from', today()->startOfMonth()->toDateString()))->startOfDay();
        $to   = Carbon::parse($request->input('to', today()->toDateString()))->endOfDay();
        $personalId = $request->input('personal_id');
        $status = $request->input('status');

        $query = Commission::with(['specialist', 'sale', 'saleItem.service'])
            ->whereBetween('created_at', [$from, $to])
            ->orderByDesc('created_at');

        if ($personalId) {
            $query->where('personal_id', $personalId);
        }
        if ($status && isset(Commission::STATUSES[$status])) {
            $query->where('status', $status);
        }

        $totales = [
            'pendiente' => (float) (clone $query)->where('status', 'pendiente')->sum('amount'),
            'pagada'    => (float) (clone $query)->where('status', 'pagada')->sum('amount'),
        ];

        // Resumen por especialista (lo que hay que pagar a cada uno)
        $porEspecialista = Commission::selectRaw('personal_id, status, SUM(amount) as monto, COUNT(*) as n')
            ->whereBetween('created_at', [$from, $to])
            ->when($personalId, fn ($q) => $q->where('personal_id', $personalId))
            ->groupBy('personal_id', 'status')
            ->with('specialist')->get()
            ->groupBy('personal_id');

        return view('staff.commissions.index', [
            'commissions' => $query->paginate(20)->withQueryString(),
            'noCompany' => false,
            'totales' => $totales,
            'porEspecialista' => $porEspecialista,
            'specialists' => Personal::where('company_id', $companyId)->orderBy('full_name')->get(),
            'filters' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'personal_id' => $personalId,
                'status' => $status,
            ],
        ]);
    }

    /** Marca una comision como pagada. */
    public function pay(Commission $commission)
    {
        $this->authorizeCommission($commission);

        if ($commission->isPaid()) {
            return back()->withErrors(['error' => 'Esta comision ya fue pagada.']);
        }

        $commission->update([
            'status' => 'pagada',
            'paid_at' => now(),
            'paid_by' => auth()->id(),
        ]);

        return back()->with('success', 'Comision marcada como pagada.');
    }

    /** Paga todas las comisiones pendientes de un especialista en el periodo. */
    public function payBulk(Request $request)
    {
        $companyId = auth()->user()->getCurrentCompany()?->id;
        if (!$companyId) {
            return back()->withErrors(['error' => 'Debes operar dentro de una empresa.']);
        }

        $validated = $request->validate([
            'personal_id' => ['required', 'exists:personals,id'],
            'from' => ['required', 'date'],
            'to' => ['required', 'date'],
        ]);

        $n = Commission::where('personal_id', $validated['personal_id'])
            ->where('status', 'pendiente')
            ->whereBetween('created_at', [
                Carbon::parse($validated['from'])->startOfDay(),
                Carbon::parse($validated['to'])->endOfDay(),
            ])
            ->update([
                'status' => 'pagada',
                'paid_at' => now(),
                'paid_by' => auth()->id(),
            ]);

        return back()->with('success', "Se marcaron {$n} comision(es) como pagadas.");
    }

    protected function authorizeCommission(Commission $commission): void
    {
        if ($commission->company_id !== auth()->user()->getCurrentCompany()?->id) {
            abort(403);
        }
    }
}
