<?php

namespace App\Http\Controllers\Crm;

use App\Http\Controllers\Controller;
use App\Models\ClientMembership;
use App\Models\MembershipPlan;
use App\Models\Service;
use App\Models\ServiceCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/** Catálogo de planes de membresía que vende el salón. */
class MembershipPlanController extends Controller
{
    public function index()
    {
        $companyId = $this->companyId();
        if (!$companyId) {
            return view('crm.memberships.plans.index', ['noCompany' => true]);
        }

        $plans = MembershipPlan::with(['sessions.service', 'category'])
            ->where('company_id', $companyId)
            ->orderBy('sort_order')->orderBy('name')
            ->get();

        // Membresías vigentes por plan, para ver qué se está usando.
        $activeCounts = ClientMembership::where('company_id', $companyId)
            ->where('status', 'active')
            ->where('starts_on', '<=', today())->where('ends_on', '>=', today())
            ->selectRaw('membership_plan_id, COUNT(*) AS n')->groupBy('membership_plan_id')
            ->pluck('n', 'membership_plan_id');

        return view('crm.memberships.plans.index', [
            'noCompany'    => false,
            'plans'        => $plans,
            'activeCounts' => $activeCounts,
        ]);
    }

    public function create()
    {
        return view('crm.memberships.plans.form', $this->formData(new MembershipPlan([
            'duration_days' => 30, 'discount_scope' => 'services', 'discount_percent' => 0, 'active' => true,
        ])));
    }

    public function store(Request $request)
    {
        $companyId = $this->companyId();
        abort_unless($companyId, 403);

        $data = $this->validatePlan($request, $companyId);

        DB::transaction(function () use ($data, $companyId) {
            $plan = MembershipPlan::create($data['plan'] + ['company_id' => $companyId, 'created_by' => auth()->id()]);
            $this->syncSessions($plan, $data['sessions']);
        });

        return redirect()->route('memberships.plans.index')
            ->with('success', 'Plan de membresía creado. Ya se puede vender desde el punto de venta.');
    }

    public function edit(MembershipPlan $plan)
    {
        $this->authorizePlan($plan);

        return view('crm.memberships.plans.form', $this->formData($plan->load('sessions')));
    }

    public function update(Request $request, MembershipPlan $plan)
    {
        $this->authorizePlan($plan);
        $data = $this->validatePlan($request, $plan->company_id);

        DB::transaction(function () use ($plan, $data) {
            $plan->update($data['plan']);
            $this->syncSessions($plan, $data['sessions']);
        });

        return redirect()->route('memberships.plans.index')
            ->with('success', 'Plan actualizado. Las membresías ya vendidas conservan sus condiciones.');
    }

    public function destroy(MembershipPlan $plan)
    {
        $this->authorizePlan($plan);
        $plan->delete(); // borrado lógico: las membresías vendidas lo siguen referenciando

        return redirect()->route('memberships.plans.index')
            ->with('success', 'Plan eliminado. Las membresías ya vendidas siguen vigentes con sus condiciones.');
    }

    // ---- Helpers ----

    protected function validatePlan(Request $request, int $companyId): array
    {
        $inCompany = fn (string $table) => Rule::exists($table, 'id')->where('company_id', $companyId);

        $v = $request->validate([
            'name'                => ['required', 'string', 'max:120'],
            'description'         => ['nullable', 'string', 'max:1000'],
            'price'               => ['required', 'numeric', 'min:0'],
            'duration_days'       => ['required', 'integer', 'min:1', 'max:3650'],
            'discount_percent'    => ['required', 'numeric', 'min:0', 'max:100'],
            'discount_scope'      => ['required', Rule::in(array_keys(MembershipPlan::SCOPES))],
            'service_category_id' => ['nullable', 'required_if:discount_scope,category', $inCompany('service_categories')],
            'sort_order'          => ['nullable', 'integer', 'min:0', 'max:999'],
            'sessions'                => ['nullable', 'array'],
            'sessions.*.service_id'   => ['nullable', $inCompany('services')],
            'sessions.*.quantity'     => ['nullable', 'integer', 'min:1', 'max:999'],
        ], [
            'service_category_id.required_if' => 'Elige la categoría a la que se aplica el descuento.',
            'discount_percent.max'            => 'El descuento no puede superar el 100 %.',
        ]);

        // Una fila por servicio; las incompletas se ignoran y los repetidos se suman.
        $sessions = collect($v['sessions'] ?? [])
            ->filter(fn ($s) => !empty($s['service_id']) && !empty($s['quantity']))
            ->groupBy('service_id')
            ->map(fn ($rows) => (int) $rows->sum('quantity'));

        if ((float) $v['discount_percent'] <= 0 && $sessions->isEmpty()) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'discount_percent' => 'La membresía debe dar algún beneficio: un descuento o sesiones incluidas.',
            ]);
        }

        return [
            'plan' => [
                'name'                => $v['name'],
                'description'         => $v['description'] ?? null,
                'price'               => $v['price'],
                'duration_days'       => $v['duration_days'],
                'discount_percent'    => $v['discount_percent'],
                'discount_scope'      => $v['discount_scope'],
                'service_category_id' => $v['discount_scope'] === 'category' ? $v['service_category_id'] : null,
                'sort_order'          => (int) ($v['sort_order'] ?? 0),
                'active'              => $request->boolean('active'),
            ],
            'sessions' => $sessions,
        ];
    }

    protected function syncSessions(MembershipPlan $plan, $sessions): void
    {
        $plan->sessions()->whereNotIn('service_id', $sessions->keys())->delete();

        foreach ($sessions as $serviceId => $quantity) {
            $plan->sessions()->updateOrCreate(['service_id' => $serviceId], ['quantity' => $quantity]);
        }
    }

    protected function formData(MembershipPlan $plan): array
    {
        $companyId = $this->companyId();

        return [
            'plan'       => $plan,
            'services'   => Service::where('company_id', $companyId)->where('active', true)->orderBy('name')->get(['id', 'name', 'price']),
            'categories' => ServiceCategory::where('company_id', $companyId)->orderBy('name')->get(['id', 'name']),
        ];
    }

    protected function authorizePlan(MembershipPlan $plan): void
    {
        abort_if($plan->company_id !== $this->companyId(), 403);
    }

    protected function companyId(): ?int
    {
        return auth()->user()->getCurrentCompany()?->id;
    }
}
