<?php

namespace App\Http\Controllers\Commissions;

use App\Http\Controllers\Controller;
use App\Models\CommissionRule;
use App\Models\Personal;
use App\Models\Service;
use App\Models\ServiceCategory;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CommissionRuleController extends Controller
{
    public function index()
    {
        $companyId = $this->companyId();
        if (!$companyId) {
            return view('commissions.rules.index', ['noCompany' => true]);
        }

        // Ordenadas por prioridad: primero las que ganan.
        $rules = CommissionRule::with(['specialist', 'service', 'category'])
            ->where('company_id', $companyId)
            ->get()
            ->sortBy([
                fn ($a, $b) => $b->priority() <=> $a->priority(),
                fn ($a, $b) => strcmp($a->whoLabel(), $b->whoLabel()),
                fn ($a, $b) => strcmp($a->targetLabel(), $b->targetLabel()),
            ])
            ->values();

        return view('commissions.rules.index', [
            'noCompany'  => false,
            'rules'      => $rules,
            'hasActive'  => $rules->contains(fn ($r) => $r->active),
        ]);
    }

    public function create()
    {
        return view('commissions.rules.form', $this->formData(new CommissionRule([
            'applies_to' => 'services',
            'type'       => 'percent',
            'active'     => true,
        ])));
    }

    public function store(Request $request)
    {
        $companyId = $this->companyId();
        if (!$companyId) {
            return back()->withErrors(['error' => 'Debes operar dentro de una empresa.']);
        }

        $data = $this->validateRule($request, $companyId);
        CommissionRule::create($data + ['company_id' => $companyId, 'created_by' => auth()->id()]);

        return redirect()->route('commission-rules.index')
            ->with('success', 'Regla creada. Se aplica a las ventas que se cobren desde ahora.');
    }

    public function edit(CommissionRule $commissionRule)
    {
        $this->authorizeRule($commissionRule);

        return view('commissions.rules.form', $this->formData($commissionRule));
    }

    public function update(Request $request, CommissionRule $commissionRule)
    {
        $this->authorizeRule($commissionRule);

        $data = $this->validateRule($request, $commissionRule->company_id, $commissionRule->id);
        $commissionRule->update($data);

        return redirect()->route('commission-rules.index')
            ->with('success', 'Regla actualizada. Las comisiones ya devengadas no cambian.');
    }

    public function destroy(CommissionRule $commissionRule)
    {
        $this->authorizeRule($commissionRule);
        $commissionRule->delete();

        return redirect()->route('commission-rules.index')
            ->with('success', 'Regla eliminada. Las comisiones ya devengadas no cambian.');
    }

    // ---- Helpers ----

    protected function validateRule(Request $request, int $companyId, ?int $ignoreId = null): array
    {
        $inCompany = fn (string $table) => Rule::exists($table, 'id')->where('company_id', $companyId);

        $data = $request->validate([
            'personal_id'         => ['nullable', $inCompany('personals')],
            'applies_to'          => ['required', Rule::in(array_keys(CommissionRule::APPLIES_TO))],
            'service_id'          => ['nullable', 'required_if:applies_to,service', $inCompany('services')],
            'service_category_id' => ['nullable', 'required_if:applies_to,category', $inCompany('service_categories')],
            'type'                => ['required', Rule::in(array_keys(CommissionRule::TYPES))],
            'value'               => ['required', 'numeric', 'min:0', $request->input('type') === 'percent' ? 'max:100' : 'max:99999999'],
            'valid_from'          => ['nullable', 'date'],
            'valid_until'         => ['nullable', 'date', 'after_or_equal:valid_from'],
            'notes'               => ['nullable', 'string', 'max:1000'],
        ], [
            'service_id.required_if'          => 'Elige el servicio al que se aplica la regla.',
            'service_category_id.required_if' => 'Elige la categoría a la que se aplica la regla.',
            'value.max'                       => 'Un porcentaje no puede superar 100.',
            'valid_until.after_or_equal'      => 'La fecha "hasta" no puede ser anterior a "desde".',
        ]);

        // Cada objetivo guarda solo el campo que le corresponde.
        $data['personal_id'] = $data['personal_id'] ?? null;
        $data['service_id'] = $data['applies_to'] === 'service' ? $data['service_id'] : null;
        $data['service_category_id'] = $data['applies_to'] === 'category' ? $data['service_category_id'] : null;
        $data['active'] = $request->boolean('active');

        if ($data['active'] && ($clash = $this->overlapping($companyId, $data, $ignoreId))) {
            throw ValidationException::withMessages([
                'applies_to' => 'Ya existe una regla activa para ' . $clash->whoLabel() . ' · ' . $clash->targetLabel()
                    . ' con vigencia ' . mb_strtolower($clash->validityLabel()) . '. Desactívala o ajusta las fechas para que no se superpongan.',
            ]);
        }

        return $data;
    }

    /**
     * Dos reglas activas con la misma combinación (profesional, objetivo) y
     * vigencias que se superponen harían el resultado ambiguo: se impide.
     */
    protected function overlapping(int $companyId, array $data, ?int $ignoreId): ?CommissionRule
    {
        $from = $data['valid_from'] ?? null;
        $until = $data['valid_until'] ?? null;

        return CommissionRule::where('company_id', $companyId)
            ->where('active', true)
            ->where('applies_to', $data['applies_to'])
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->where(fn ($q) => $data['personal_id'] ? $q->where('personal_id', $data['personal_id']) : $q->whereNull('personal_id'))
            ->where(fn ($q) => $data['service_id'] ? $q->where('service_id', $data['service_id']) : $q->whereNull('service_id'))
            ->where(fn ($q) => $data['service_category_id'] ? $q->where('service_category_id', $data['service_category_id']) : $q->whereNull('service_category_id'))
            // [a, b] y [c, d] se superponen si a <= d y c <= b (un extremo vacío es infinito).
            ->where(fn ($q) => $until ? $q->whereNull('valid_from')->orWhere('valid_from', '<=', $until) : $q)
            ->where(fn ($q) => $from ? $q->whereNull('valid_until')->orWhere('valid_until', '>=', $from) : $q)
            ->first();
    }

    protected function formData(CommissionRule $rule): array
    {
        $companyId = $this->companyId();

        return [
            'rule'        => $rule,
            'specialists' => Personal::where('company_id', $companyId)->where('active', true)->orderBy('full_name')->get(),
            'services'    => Service::where('company_id', $companyId)->orderBy('name')->get(),
            'categories'  => ServiceCategory::where('company_id', $companyId)->orderBy('name')->get(),
            'sellsProducts' => (bool) auth()->user()->getCurrentCompany()?->planAllows('inventory'),
        ];
    }

    protected function authorizeRule(CommissionRule $rule): void
    {
        if ($rule->company_id !== $this->companyId()) {
            abort(403);
        }
    }

    protected function companyId(): ?int
    {
        return auth()->user()->getCurrentCompany()?->id;
    }
}
