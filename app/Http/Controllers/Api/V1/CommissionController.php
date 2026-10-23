<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Commission;
use App\Models\CompanySetting;
use App\Models\Sale;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Comisiones propias del profesional conectado. Igual que "Mis comisiones"
 * de la web: siempre filtrado por su ficha, nunca por parámetro.
 */
class CommissionController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        if ($r = $this->planMissing('commissions')) {
            return $r;
        }

        $personal = $this->ownPersonal();
        if (!$personal) {
            return $this->noPersonal();
        }

        $from = $request->filled('from') ? Carbon::parse($request->input('from')) : today()->startOfMonth();
        $to = $request->filled('to') ? Carbon::parse($request->input('to')) : today()->endOfMonth();
        $companyId = $this->company()->id;

        $commissions = Commission::with(['sale', 'saleItem.service', 'saleItem.product', 'settlement'])
            ->where('company_id', $companyId)
            ->where('personal_id', $personal->id)
            ->whereBetween('accrued_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->orderByDesc('accrued_at')
            ->get();

        $tips = Sale::where('company_id', $companyId)
            ->where('personal_id', $personal->id)
            ->where('status', '!=', 'anulada')
            ->where('tip', '>', 0)
            ->whereBetween('sale_date', [$from->toDateString(), $to->toDateString()])
            ->sum('tip');

        return response()->json([
            'from'     => $from->toDateString(),
            'to'       => $to->toDateString(),
            'currency' => CompanySetting::current($companyId)->currency_symbol,
            'kpis'     => [
                'earned'  => round((float) $commissions->sum('amount'), 2),
                'pending' => round((float) $commissions->filter->isPending()->sum('amount'), 2),
                'settled' => round((float) $commissions->reject->isPending()->sum('amount'), 2),
                'tips'    => round((float) $tips, 2),
            ],
            'items' => $commissions->map(fn ($c) => [
                'id'         => $c->id,
                'date'       => $c->accrued_at?->toDateString(),
                'sale'       => $c->sale?->sale_number,
                'concept'    => $c->concept(),
                'base'       => (float) $c->base,
                'rate'       => $c->rateLabel(),
                'amount'     => (float) $c->amount,
                'pending'    => $c->isPending(),
                'status'     => $c->statusLabel(),
                'reversal'   => $c->isReversal(),
            ])->values(),
        ]);
    }
}
