<?php

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Controller;
use App\Models\Promotion;
use App\Models\SaleItem;
use App\Models\Service;
use App\Models\ServiceCategory;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Promociones automáticas del punto de venta (por día, fechas y servicio). */
class PromotionController extends Controller
{
    public function index()
    {
        $companyId = $this->companyId();
        if (!$companyId) {
            return view('marketing.promotions.index', ['noCompany' => true]);
        }

        $promotions = Promotion::with(['service', 'category'])->where('company_id', $companyId)->orderByDesc('active')->latest('id')->get();
        $stats = SaleItem::query()->join('sales', 'sales.id', '=', 'sale_details.sale_id')
            ->where('sales.company_id', $companyId)->where('sales.status', '!=', 'anulada')->whereNotNull('sale_details.promotion_id')
            ->selectRaw('sale_details.promotion_id, COUNT(*) AS uses, SUM(sale_details.promotion_discount) AS discount')
            ->groupBy('sale_details.promotion_id')->get()->keyBy('promotion_id');

        return view('marketing.promotions.index', ['noCompany' => false, 'promotions' => $promotions, 'stats' => $stats]);
    }

    public function create()
    {
        return view('marketing.promotions.form', $this->formData(new Promotion(['discount_type' => 'percent', 'applies_to' => 'services', 'active' => true])));
    }

    public function store(Request $request)
    {
        $companyId = $this->companyId();
        abort_unless($companyId, 403);
        Promotion::create($this->validated($request, $companyId) + ['company_id' => $companyId, 'created_by' => auth()->id()]);

        return redirect()->route('marketing.promotions.index')->with('success', 'Promoción creada: se aplicará sola en el punto de venta.');
    }

    public function edit(Promotion $promotion)
    {
        $this->authorizePromotion($promotion);

        return view('marketing.promotions.form', $this->formData($promotion));
    }

    public function update(Request $request, Promotion $promotion)
    {
        $this->authorizePromotion($promotion);
        $promotion->update($this->validated($request, $promotion->company_id));

        return redirect()->route('marketing.promotions.index')->with('success', 'Promoción actualizada.');
    }

    public function destroy(Promotion $promotion)
    {
        $this->authorizePromotion($promotion);
        $promotion->delete();

        return redirect()->route('marketing.promotions.index')->with('success', 'Promoción eliminada.');
    }

    protected function validated(Request $request, int $companyId): array
    {
        $v = $request->validate([
            'name'                => ['required', 'string', 'max:120'],
            'discount_type'       => ['required', 'in:percent,amount'],
            'value'               => ['required', 'numeric', 'min:0.01', $request->input('discount_type') === 'percent' ? 'max:100' : 'max:999999'],
            'applies_to'          => ['required', Rule::in(array_keys(Promotion::APPLIES))],
            'service_id'          => ['nullable', 'required_if:applies_to,service', Rule::exists('services', 'id')->where('company_id', $companyId)],
            'service_category_id' => ['nullable', 'required_if:applies_to,category', Rule::exists('service_categories', 'id')->where('company_id', $companyId)],
            'weekdays'            => ['nullable', 'array'],
            'weekdays.*'          => ['integer', 'between:1,7'],
            'starts_on'           => ['nullable', 'date'],
            'ends_on'             => ['nullable', 'date', 'after_or_equal:starts_on'],
        ], [
            'service_id.required_if'          => 'Elige el servicio de la promoción.',
            'service_category_id.required_if' => 'Elige la categoría de la promoción.',
            'value.max'                       => 'Un descuento en porcentaje no puede superar el 100 %.',
        ]);

        $days = collect($v['weekdays'] ?? [])->map(fn ($d) => (int) $d)->unique()->sort()->values();

        return [
            'name'                => $v['name'],
            'discount_type'       => $v['discount_type'],
            'value'               => $v['value'],
            'applies_to'          => $v['applies_to'],
            'service_id'          => $v['applies_to'] === 'service' ? $v['service_id'] : null,
            'service_category_id' => $v['applies_to'] === 'category' ? $v['service_category_id'] : null,
            // Todos los días marcados = sin restricción.
            'weekdays'            => $days->isEmpty() || $days->count() === 7 ? null : $days->implode(','),
            'starts_on'           => $v['starts_on'] ?? null,
            'ends_on'             => $v['ends_on'] ?? null,
            'active'              => $request->boolean('active'),
        ];
    }

    protected function formData(Promotion $promotion): array
    {
        $companyId = $this->companyId();

        return [
            'promotion'  => $promotion,
            'services'   => Service::where('company_id', $companyId)->where('active', true)->orderBy('name')->get(['id', 'name']),
            'categories' => ServiceCategory::where('company_id', $companyId)->orderBy('name')->get(['id', 'name']),
        ];
    }

    protected function authorizePromotion(Promotion $promotion): void
    {
        abort_if($promotion->company_id !== $this->companyId(), 403);
    }

    protected function companyId(): ?int
    {
        return auth()->user()->getCurrentCompany()?->id;
    }
}
