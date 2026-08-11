<?php

namespace App\Http\Controllers\System;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Subscription;
use App\Models\SubscriptionPayment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SubscriptionPaymentController extends Controller
{
    public function __construct()
    {
        $this->middleware('check-role:super_admin');
    }

    /**
     * Registra un pago manual (queda pendiente de confirmación).
     */
    public function store(Request $request, Company $company)
    {
        $subscription = $company->currentSubscription();
        if (!$subscription) {
            return back()->withErrors(['error' => 'La empresa no tiene una suscripción/plan asignado.']);
        }

        $validated = $request->validate([
            'amount' => 'required|numeric|min:0',
            'method' => 'required|in:transferencia,qr,efectivo',
            'reference' => 'nullable|string|max:255',
            'paid_at' => 'nullable|date',
            'proof' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'notes' => 'nullable|string|max:2000',
            'confirm_now' => 'nullable|boolean',
        ]);

        $payment = new SubscriptionPayment([
            'subscription_id' => $subscription->id,
            'company_id' => $company->id,
            'amount' => $validated['amount'],
            'currency' => $subscription->plan->currency ?? config('saas.currency'),
            'method' => $validated['method'],
            'reference' => $validated['reference'] ?? null,
            'paid_at' => $validated['paid_at'] ?? now(),
            'status' => 'pending',
            'notes' => $validated['notes'] ?? null,
            'created_by' => auth()->id(),
        ]);

        if ($request->hasFile('proof')) {
            $payment->proof = $request->file('proof')->store('payments', 'public');
        }

        $payment->save();

        if ($request->boolean('confirm_now')) {
            $this->applyConfirmation($payment);
            return redirect()->route('subscriptions.show', $company)->with('success', 'Pago registrado y confirmado. Suscripción renovada.');
        }

        return redirect()->route('subscriptions.show', $company)->with('success', 'Pago registrado (pendiente de confirmación).');
    }

    /**
     * Confirma un pago pendiente y renueva la suscripción.
     */
    public function confirm(SubscriptionPayment $payment)
    {
        if ($payment->status === 'confirmed') {
            return back()->withErrors(['error' => 'Este pago ya fue confirmado.']);
        }

        $this->applyConfirmation($payment);

        return back()->with('success', 'Pago confirmado. Suscripción renovada.');
    }

    public function reject(SubscriptionPayment $payment)
    {
        if ($payment->status === 'confirmed') {
            return back()->withErrors(['error' => 'No puedes rechazar un pago ya confirmado.']);
        }

        $payment->update(['status' => 'rejected']);

        return back()->with('success', 'Pago rechazado.');
    }

    /**
     * Aplica la confirmación: marca el pago y extiende la suscripción.
     */
    protected function applyConfirmation(SubscriptionPayment $payment): void
    {
        DB::transaction(function () use ($payment) {
            $subscription = $payment->subscription()->with('plan')->first();
            $billingDays = (int) ($subscription->plan->billing_days ?? 30);

            // El nuevo periodo arranca en el vencimiento vigente (si aún es futuro) o desde hoy.
            $currentEnd = $subscription->effectiveEndsAt();
            $periodStart = ($currentEnd && $currentEnd->isFuture()) ? $currentEnd->copy() : now();
            $newEnd = $periodStart->copy()->addDays($billingDays);

            $subscription->update([
                'status' => 'active',
                'starts_at' => $subscription->starts_at ?? now(),
                'ends_at' => $newEnd,
            ]);

            $payment->update([
                'status' => 'confirmed',
                'confirmed_by' => auth()->id(),
                'confirmed_at' => now(),
                'period_start' => $periodStart->toDateString(),
                'period_end' => $newEnd->toDateString(),
            ]);
        });
    }
}
