<?php

namespace App\Http\Controllers\Organization;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\PaymentMethod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class PaymentMethodController extends Controller
{
    public function index()
    {
        $methods = PaymentMethod::with('company')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return view('organization.payment_methods.index', [
            'methods'   => $methods,
            'companyId' => $this->companyId(),
            'fallback'  => PaymentMethod::fallbackOptions(),
        ]);
    }

    public function create()
    {
        return view('organization.payment_methods.create', [
            'method'    => new PaymentMethod(['active' => true, 'affects_cash' => false, 'sort_order' => 0]),
            'companies' => $this->companiesForUser(),
            'icons'     => PaymentMethod::iconOptions(),
        ]);
    }

    public function store(Request $request)
    {
        $companyId = $this->resolveCompanyId($request);

        if (!$companyId) {
            return back()->withInput()->withErrors(['company_id' => 'Debes seleccionar una empresa.']);
        }

        $validated = $this->validateData($request, $companyId);

        try {
            DB::transaction(function () use ($validated, $companyId, $request) {
                $method = PaymentMethod::create([
                    'company_id'   => $companyId,
                    'name'         => $validated['name'],
                    'code'         => $validated['code'],
                    'icon'         => $validated['icon'] ?? null,
                    'affects_cash' => $request->boolean('affects_cash'),
                    'is_default'   => $request->boolean('is_default'),
                    'active'       => $request->boolean('active', true),
                    'sort_order'   => (int) ($validated['sort_order'] ?? 0),
                    'created_by'   => auth()->id(),
                ]);

                $this->syncDefault($method);
            });

            return redirect()->route('payment-methods.index')->with('success', 'Método de pago creado.');
        } catch (\Throwable $e) {
            Log::error('Error al crear método de pago', ['message' => $e->getMessage()]);
            return back()->withInput()->withErrors(['error' => 'No fue posible crear el método de pago.']);
        }
    }

    public function edit(PaymentMethod $paymentMethod)
    {
        $this->authorizeMethod($paymentMethod);

        return view('organization.payment_methods.edit', [
            'method'    => $paymentMethod,
            'companies' => $this->companiesForUser(),
            'icons'     => PaymentMethod::iconOptions(),
        ]);
    }

    public function update(Request $request, PaymentMethod $paymentMethod)
    {
        $this->authorizeMethod($paymentMethod);

        $validated = $this->validateData($request, $paymentMethod->company_id, $paymentMethod->id);

        try {
            DB::transaction(function () use ($validated, $request, $paymentMethod) {
                $paymentMethod->update([
                    'name'         => $validated['name'],
                    'code'         => $validated['code'],
                    'icon'         => $validated['icon'] ?? null,
                    'affects_cash' => $request->boolean('affects_cash'),
                    'is_default'   => $request->boolean('is_default'),
                    'active'       => $request->boolean('active', true),
                    'sort_order'   => (int) ($validated['sort_order'] ?? 0),
                ]);

                $this->syncDefault($paymentMethod);
            });

            return redirect()->route('payment-methods.index')->with('success', 'Método de pago actualizado.');
        } catch (\Throwable $e) {
            Log::error('Error al actualizar método de pago', ['id' => $paymentMethod->id, 'message' => $e->getMessage()]);
            return back()->withInput()->withErrors(['error' => 'No fue posible actualizar el método de pago.']);
        }
    }

    public function destroy(PaymentMethod $paymentMethod)
    {
        $this->authorizeMethod($paymentMethod);

        $used = DB::table('sales')
            ->where('company_id', $paymentMethod->company_id)
            ->where('payment_method', $paymentMethod->code)
            ->exists();

        if ($used) {
            return back()->withErrors(['error' => 'Este método ya tiene ventas registradas. Desactívalo en lugar de eliminarlo.']);
        }

        $paymentMethod->delete();

        return redirect()->route('payment-methods.index')->with('success', 'Método de pago eliminado.');
    }

    /** Carga el catálogo base (efectivo, tarjeta, transferencia, QR). */
    public function seedDefaults()
    {
        $companyId = $this->companyId();

        if (!$companyId) {
            return back()->withErrors(['error' => 'Selecciona una empresa para cargar los métodos base.']);
        }

        PaymentMethod::seedDefaultsFor($companyId);

        return redirect()->route('payment-methods.index')->with('success', 'Métodos base cargados.');
    }

    protected function validateData(Request $request, ?int $companyId, ?int $ignoreId = null): array
    {
        $request->merge(['code' => Str::slug((string) ($request->input('code') ?: $request->input('name')), '_')]);

        return $request->validate([
            'company_id'   => ['nullable', 'exists:companies,id'],
            'name'         => ['required', 'string', 'max:80'],
            'code'         => [
                'required', 'string', 'max:30',
                Rule::unique('payment_methods', 'code')
                    ->where(fn ($q) => $q->where('company_id', $companyId)->whereNull('deleted_at'))
                    ->ignore($ignoreId),
            ],
            'icon'         => ['nullable', 'string', 'max:40'],
            'sort_order'   => ['nullable', 'integer', 'min:0', 'max:999'],
            'affects_cash' => ['nullable', 'boolean'],
            'is_default'   => ['nullable', 'boolean'],
            'active'       => ['nullable', 'boolean'],
        ], [
            'code.unique' => 'Ya existe un método con ese código en la empresa.',
        ]);
    }

    /** Solo un método puede quedar como predeterminado por empresa. */
    protected function syncDefault(PaymentMethod $method): void
    {
        if (!$method->is_default) {
            return;
        }

        PaymentMethod::withoutGlobalScopes()
            ->where('company_id', $method->company_id)
            ->where('id', '!=', $method->id)
            ->update(['is_default' => false]);
    }

    protected function companyId(): ?int
    {
        $user = auth()->user();

        return $user->is_super_admin
            ? (request('company_id') ? (int) request('company_id') : null)
            : $user->getCurrentCompany()?->id;
    }

    protected function resolveCompanyId(Request $request): ?int
    {
        $user = auth()->user();

        return $user->is_super_admin
            ? ((int) $request->input('company_id') ?: null)
            : $user->getCurrentCompany()?->id;
    }

    protected function companiesForUser()
    {
        $user = auth()->user();

        return $user->is_super_admin
            ? Company::orderBy('name')->get()
            : collect([$user->getCurrentCompany()])->filter();
    }

    protected function authorizeMethod(PaymentMethod $method): void
    {
        $user = auth()->user();

        if (!$user->is_super_admin && $method->company_id !== $user->getCurrentCompany()?->id) {
            abort(403);
        }
    }
}
