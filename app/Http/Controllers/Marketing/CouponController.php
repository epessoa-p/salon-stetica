<?php

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Coupon;
use App\Models\Sale;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/** Cupones de descuento: se escriben en el punto de venta. */
class CouponController extends Controller
{
    public function index(Request $request)
    {
        $companyId = $this->companyId();
        if (!$companyId) {
            return view('marketing.coupons.index', ['noCompany' => true]);
        }

        $coupons = Coupon::with('client')->where('company_id', $companyId)->latest('id')->paginate(25);
        $ids = $coupons->getCollection()->pluck('id');
        $stats = Sale::where('company_id', $companyId)->where('status', '!=', 'anulada')->whereIn('coupon_id', $ids)
            ->selectRaw('coupon_id, COUNT(*) AS uses, SUM(coupon_discount) AS discount, SUM(total) AS sales')
            ->groupBy('coupon_id')->get()->keyBy('coupon_id');

        return view('marketing.coupons.index', ['noCompany' => false, 'coupons' => $coupons, 'stats' => $stats]);
    }

    public function create()
    {
        return view('marketing.coupons.form', [
            'coupon'  => new Coupon(['discount_type' => 'percent', 'applies_to' => 'all', 'active' => true, 'code' => $this->suggestCode()]),
            'clients' => collect(),
        ]);
    }

    public function store(Request $request)
    {
        $companyId = $this->companyId();
        abort_unless($companyId, 403);
        Coupon::create($this->validated($request, $companyId) + ['company_id' => $companyId, 'created_by' => auth()->id()]);

        return redirect()->route('marketing.coupons.index')->with('success', 'Cupón creado. Ya se puede usar en el punto de venta.');
    }

    public function edit(Coupon $coupon)
    {
        $this->authorizeCoupon($coupon);

        return view('marketing.coupons.form', ['coupon' => $coupon, 'clients' => collect([$coupon->client])->filter()]);
    }

    public function update(Request $request, Coupon $coupon)
    {
        $this->authorizeCoupon($coupon);
        $coupon->update($this->validated($request, $coupon->company_id, $coupon->id));

        return redirect()->route('marketing.coupons.index')->with('success', 'Cupón actualizado.');
    }

    public function destroy(Coupon $coupon)
    {
        $this->authorizeCoupon($coupon);
        $coupon->delete();

        return redirect()->route('marketing.coupons.index')->with('success', 'Cupón eliminado.');
    }

    protected function validated(Request $request, int $companyId, ?int $ignoreId = null): array
    {
        $request->merge(['code' => mb_strtoupper(preg_replace('/\s+/', '', (string) $request->input('code')))]);

        $v = $request->validate([
            'code'                => ['required', 'string', 'max:40', 'regex:/^[A-Z0-9\-_]+$/',
                Rule::unique('coupons', 'code')->where('company_id', $companyId)->whereNull('deleted_at')->ignore($ignoreId)],
            'name'                => ['required', 'string', 'max:120'],
            'discount_type'       => ['required', 'in:percent,amount'],
            'value'               => ['required', 'numeric', 'min:0.01', $request->input('discount_type') === 'percent' ? 'max:100' : 'max:999999'],
            'applies_to'          => ['required', Rule::in(array_keys(Coupon::APPLIES))],
            'min_purchase'        => ['nullable', 'numeric', 'min:0'],
            'max_discount'        => ['nullable', 'numeric', 'min:0'],
            'starts_on'           => ['nullable', 'date'],
            'ends_on'             => ['nullable', 'date', 'after_or_equal:starts_on'],
            'max_uses'            => ['nullable', 'integer', 'min:1'],
            'max_uses_per_client' => ['nullable', 'integer', 'min:1'],
            'client_id'           => ['nullable', Rule::exists('clients', 'id')->where('company_id', $companyId)],
            'notes'               => ['nullable', 'string', 'max:255'],
        ], [
            'code.regex'  => 'El código solo puede tener letras, números, guiones y guion bajo.',
            'code.unique' => 'Ya existe un cupón con ese código.',
            'value.max'   => 'Un descuento en porcentaje no puede superar el 100 %.',
        ]);

        return $v + ['active' => $request->boolean('active')];
    }

    protected function suggestCode(): string
    {
        return 'GLAM' . strtoupper(Str::random(5));
    }

    protected function authorizeCoupon(Coupon $coupon): void
    {
        abort_if($coupon->company_id !== $this->companyId(), 403);
    }

    protected function companyId(): ?int
    {
        return auth()->user()->getCurrentCompany()?->id;
    }
}
