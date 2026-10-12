<?php

namespace App\Http\Controllers\Commissions;

use App\Http\Controllers\Controller;
use App\Models\CashSession;
use App\Models\Commission;
use App\Models\Personal;
use App\Models\Sale;
use App\Services\CommissionService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class CommissionController extends Controller
{
    public function __construct(protected CommissionService $service)
    {
    }

    /**
     * Resumen del periodo por profesional y, si se elige uno, su detalle y
     * el formulario para liquidarle.
     */
    public function index(Request $request)
    {
        $companyId = $this->companyId();
        if (!$companyId) {
            return view('commissions.commissions.index', ['noCompany' => true]);
        }

        [$from, $to] = $this->period($request);
        $personalId = $request->integer('personal_id') ?: null;

        $summary = $this->summary($companyId, $from, $to);

        $data = [
            'noCompany'   => false,
            'from'        => $from,
            'to'          => $to,
            'summary'     => $summary,
            'totals'      => [
                'count'        => $summary->sum('count'),
                'earned'       => $summary->sum('earned'),
                'pending'      => $summary->sum('pending'),
                'tips'         => $summary->sum('tips'),
                'tips_pending' => $summary->sum('tips_pending'),
            ],
            'specialists' => Personal::where('company_id', $companyId)->orderBy('full_name')->get(),
            'personal'    => null,
        ];

        if ($personalId) {
            $personal = Personal::where('company_id', $companyId)->findOrFail($personalId);

            $data['personal'] = $personal;
            $data['detail'] = $this->detail($companyId, $personal->id, $from, $to);
            $data['tipSales'] = $this->tipSales($companyId, $personal->id, $from, $to);
            $data['pendingTotal'] = (float) $this->service->pendingCommissions($companyId, $personal->id, $from, $to)->sum('amount');
            $data['pendingTips'] = (float) $this->service->pendingTips($companyId, $personal->id, $from, $to)->sum('tip');
            $data['usesCash'] = $this->service->companyUsesCash($companyId);
            $data['openSessions'] = CashSession::with('register')
                ->where('status', 'open')
                ->whereHas('register', fn ($q) => $q->where('company_id', $companyId))
                ->get();
        }

        return view('commissions.commissions.index', $data);
    }

    /**
     * Vista del propio profesional: siempre filtrada por la ficha de personal
     * vinculada al usuario conectado. No acepta otro profesional por parámetro.
     */
    public function mine(Request $request)
    {
        $companyId = $this->companyId();
        $personal = $companyId
            ? Personal::where('company_id', $companyId)->where('user_id', auth()->id())->first()
            : null;

        if (!$personal) {
            return view('commissions.commissions.mine', ['personal' => null]);
        }

        [$from, $to] = $this->period($request);
        $commissions = $this->detail($companyId, $personal->id, $from, $to);
        $tipSales = $this->tipSales($companyId, $personal->id, $from, $to);

        return view('commissions.commissions.mine', [
            'personal'    => $personal,
            'from'        => $from,
            'to'          => $to,
            'commissions' => $commissions,
            'tipSales'    => $tipSales,
            'kpis'        => [
                'earned'  => (float) $commissions->sum('amount'),
                'pending' => (float) $commissions->filter->isPending()->sum('amount'),
                'settled' => (float) $commissions->reject->isPending()->sum('amount'),
                'tips'    => (float) $tipSales->sum('tip'),
            ],
        ]);
    }

    // ---- Consultas ----

    /**
     * Una fila por profesional con movimiento en el periodo: número de
     * comisiones, total ganado, pendiente y propinas.
     */
    protected function summary(int $companyId, Carbon $from, Carbon $to)
    {
        $range = [$from->copy()->startOfDay(), $to->copy()->endOfDay()];

        $commissions = Commission::where('company_id', $companyId)
            ->whereBetween('accrued_at', $range)
            ->selectRaw('personal_id, COUNT(*) AS n, SUM(amount) AS earned')
            ->selectRaw("SUM(CASE WHEN status = 'pendiente' AND settlement_id IS NULL THEN amount ELSE 0 END) AS pending")
            ->groupBy('personal_id')
            ->get()
            ->keyBy('personal_id');

        $tips = Sale::where('company_id', $companyId)
            ->where('status', '!=', 'anulada')
            ->where('tip', '>', 0)
            ->whereNotNull('personal_id')
            ->whereBetween('sale_date', [$from->toDateString(), $to->toDateString()])
            ->selectRaw('personal_id, SUM(tip) AS tips')
            ->selectRaw('SUM(CASE WHEN tip_settlement_id IS NULL THEN tip ELSE 0 END) AS tips_pending')
            ->groupBy('personal_id')
            ->get()
            ->keyBy('personal_id');

        $ids = $commissions->keys()->merge($tips->keys())->unique()->filter();
        $people = Personal::whereIn('id', $ids)->get()->keyBy('id');

        return $ids->map(fn ($id) => (object) [
            'personal'     => $people[$id] ?? null,
            'personal_id'  => $id,
            'count'        => (int) ($commissions[$id]->n ?? 0),
            'earned'       => round((float) ($commissions[$id]->earned ?? 0), 2),
            'pending'      => round((float) ($commissions[$id]->pending ?? 0), 2),
            'tips'         => round((float) ($tips[$id]->tips ?? 0), 2),
            'tips_pending' => round((float) ($tips[$id]->tips_pending ?? 0), 2),
        ])->sortBy(fn ($row) => $row->personal?->full_name)->values();
    }

    protected function detail(int $companyId, int $personalId, Carbon $from, Carbon $to)
    {
        return Commission::with(['sale', 'saleItem.service', 'saleItem.product', 'settlement'])
            ->where('company_id', $companyId)
            ->where('personal_id', $personalId)
            ->whereBetween('accrued_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->orderBy('accrued_at')
            ->get();
    }

    protected function tipSales(int $companyId, int $personalId, Carbon $from, Carbon $to)
    {
        return Sale::with('tipSettlement')
            ->where('company_id', $companyId)
            ->where('personal_id', $personalId)
            ->where('status', '!=', 'anulada')
            ->where('tip', '>', 0)
            ->whereBetween('sale_date', [$from->toDateString(), $to->toDateString()])
            ->orderBy('sale_date')
            ->get();
    }

    /** Periodo elegido; por defecto el mes en curso, el ciclo de pago habitual. */
    protected function period(Request $request): array
    {
        $from = $request->filled('from') ? Carbon::parse($request->input('from')) : today()->startOfMonth();
        $to = $request->filled('to') ? Carbon::parse($request->input('to')) : today()->endOfMonth();

        if ($to->lt($from)) {
            [$from, $to] = [$to, $from];
        }

        return [$from->startOfDay(), $to->startOfDay()];
    }

    protected function companyId(): ?int
    {
        return auth()->user()->getCurrentCompany()?->id;
    }
}
