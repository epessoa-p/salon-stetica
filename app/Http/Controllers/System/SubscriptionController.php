<?php

namespace App\Http\Controllers\System;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Plan;
use App\Models\Subscription;
use Illuminate\Http\Request;

class SubscriptionController extends Controller
{
    public function __construct()
    {
        $this->middleware('check-role:super_admin');
    }

    public function index()
    {
        $companies = Company::with(['subscriptions' => fn ($q) => $q->with('plan')->latest('id')])
            ->orderBy('name')
            ->paginate(15);

        return view('system.subscriptions.index', compact('companies'));
    }

    /**
     * Detalle de la suscripción de una empresa + pagos.
     */
    public function show(Company $company)
    {
        $subscription = $company->currentSubscription();
        $payments = $subscription
            ? $subscription->payments()->latest('id')->get()
            : collect();

        return view('system.subscriptions.show', compact('company', 'subscription', 'payments'));
    }

    /**
     * Formulario para asignar / cambiar el plan de una empresa.
     */
    public function edit(Company $company)
    {
        $plans = Plan::where('active', true)->orderBy('sort_order')->orderBy('price')->get();
        $subscription = $company->currentSubscription();

        return view('system.subscriptions.edit', compact('company', 'plans', 'subscription'));
    }

    /**
     * Asigna un plan: crea una nueva suscripción iniciando el periodo de prueba.
     */
    public function update(Request $request, Company $company)
    {
        $validated = $request->validate([
            'plan_id' => 'required|exists:plans,id',
            'trial_days' => 'nullable|integer|min:0',
            'notes' => 'nullable|string|max:2000',
        ]);

        $plan = Plan::findOrFail($validated['plan_id']);
        $trialDays = $validated['trial_days'] ?? $plan->trial_days ?? config('saas.default_trial_days');

        Subscription::create([
            'company_id' => $company->id,
            'plan_id' => $plan->id,
            'status' => 'trial',
            'trial_ends_at' => now()->addDays((int) $trialDays),
            'starts_at' => now(),
            'ends_at' => null,
            'amount' => $plan->price,
            'notes' => $validated['notes'] ?? null,
            'created_by' => auth()->id(),
        ]);

        return redirect()->route('subscriptions.show', $company)
            ->with('success', 'Plan asignado. La suscripción inició en periodo de prueba.');
    }
}
