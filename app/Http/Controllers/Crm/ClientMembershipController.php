<?php

namespace App\Http\Controllers\Crm;

use App\Http\Controllers\Controller;
use App\Models\ClientMembership;
use App\Services\MembershipService;
use Illuminate\Http\Request;

/** Membresías vendidas: seguimiento de vigencia y cancelación. */
class ClientMembershipController extends Controller
{
    public const FILTERS = [
        'active'   => 'Vigentes',
        'expiring' => 'Vencen en 7 días',
        'expired'  => 'Vencidas',
        'canceled' => 'Canceladas',
        'all'      => 'Todas',
    ];

    public function __construct(protected MembershipService $memberships)
    {
    }

    public function index(Request $request)
    {
        $companyId = auth()->user()->getCurrentCompany()?->id;
        if (!$companyId) {
            return view('crm.memberships.sold.index', ['noCompany' => true]);
        }

        $filter = array_key_exists($request->input('filter'), self::FILTERS) ? $request->input('filter') : 'active';
        $search = trim((string) $request->input('q'));
        $today = today()->toDateString();

        $query = ClientMembership::with(['client', 'sessions'])
            ->where('company_id', $companyId)
            ->when($search !== '', fn ($q) => $q->whereHas('client', fn ($c) => $c->where('full_name', 'like', "%{$search}%")));

        match ($filter) {
            'active'   => $query->where('status', 'active')->where('starts_on', '<=', $today)->where('ends_on', '>=', $today),
            'expiring' => $query->where('status', 'active')->where('starts_on', '<=', $today)
                                ->whereBetween('ends_on', [$today, today()->addDays(7)->toDateString()]),
            'expired'  => $query->where('status', 'active')->where('ends_on', '<', $today),
            'canceled' => $query->where('status', 'canceled'),
            default    => null,
        };

        $counts = [
            'active'   => ClientMembership::where('company_id', $companyId)->where('status', 'active')->where('starts_on', '<=', $today)->where('ends_on', '>=', $today)->count(),
            'expiring' => ClientMembership::where('company_id', $companyId)->where('status', 'active')->where('starts_on', '<=', $today)
                            ->whereBetween('ends_on', [$today, today()->addDays(7)->toDateString()])->count(),
        ];

        $memberships = $query->orderBy($filter === 'expiring' ? 'ends_on' : 'created_at', $filter === 'expiring' ? 'asc' : 'desc')
            ->paginate(20)->withQueryString();

        // Sesiones restantes de las membresías de esta página.
        $remaining = $memberships->getCollection()->mapWithKeys(fn ($m) => [$m->id => array_sum($this->memberships->remainingSessions($m))]);

        return view('crm.memberships.sold.index', [
            'noCompany'   => false,
            'memberships' => $memberships,
            'remaining'   => $remaining,
            'filter'      => $filter,
            'search'      => $search,
            'counts'      => $counts,
        ]);
    }

    /** Cancela una membresía. No reembolsa: para devolver el dinero se anula la venta. */
    public function cancel(Request $request, ClientMembership $membership)
    {
        abort_if($membership->company_id !== auth()->user()->getCurrentCompany()?->id, 403);

        if ($membership->status === 'canceled') {
            return back()->withErrors(['error' => 'La membresía ya estaba cancelada.']);
        }

        $reason = $request->validate(['reason' => ['nullable', 'string', 'max:255']])['reason'] ?? null;
        $this->memberships->cancel($membership, $reason);

        return back()->with('success', "Membresía de {$membership->client?->full_name} cancelada. No se generó ningún reembolso.");
    }
}
