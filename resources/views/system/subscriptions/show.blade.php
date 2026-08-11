@extends('layouts.app')

@section('title', 'Suscripción')

@section('page')
<div class="container-fluid" style="max-width: 1000px;">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h1 class="mb-1"><i class="bi bi-card-checklist"></i> Suscripción</h1>
            <p class="text-muted mb-0">Empresa: <strong>{{ $company->name }}</strong></p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('subscriptions.index') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Volver</a>
            <a href="{{ route('subscriptions.edit', $company) }}" class="btn btn-outline-primary"><i class="bi bi-arrow-repeat"></i> Cambiar plan</a>
        </div>
    </div>

    @if(!$subscription)
        <div class="alert alert-warning">Esta empresa no tiene un plan asignado. <a href="{{ route('subscriptions.edit', $company) }}">Asignar plan</a>.</div>
    @else
        @php $status = $subscription->computedStatus(); $end = $subscription->effectiveEndsAt(); @endphp
        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="card border-0 shadow-sm h-100"><div class="card-body">
                    <div class="text-muted small">Plan</div>
                    <div class="fw-bold">{{ $subscription->plan?->name }}</div>
                    <div class="small text-muted">{{ number_format($subscription->plan->price, 2) }} {{ $subscription->plan->currency }} / {{ $subscription->plan->billing_days }} días</div>
                </div></div>
            </div>
            <div class="col-md-3">
                <div class="card border-0 shadow-sm h-100"><div class="card-body">
                    <div class="text-muted small">Estado</div>
                    <div>@include('system.subscriptions._status', ['status' => $status])</div>
                </div></div>
            </div>
            <div class="col-md-3">
                <div class="card border-0 shadow-sm h-100"><div class="card-body">
                    <div class="text-muted small">Vence</div>
                    <div class="fw-bold">{{ $end ? $end->format('d/m/Y') : '—' }}</div>
                    <div class="small text-muted">{{ $subscription->ends_at ? 'Pagado' : 'En prueba' }}</div>
                </div></div>
            </div>
            <div class="col-md-3">
                <div class="card border-0 shadow-sm h-100"><div class="card-body">
                    <div class="text-muted small">Días restantes</div>
                    <div class="fw-bold">{{ $subscription->daysLeft() !== null ? $subscription->daysLeft() : '—' }}</div>
                </div></div>
            </div>
        </div>

        {{-- Registrar pago --}}
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body p-4">
                <h6 class="fw-bold mb-3"><i class="bi bi-cash-coin"></i> Registrar pago manual</h6>
                @if($errors->any())
                    <div class="alert alert-danger"><ul class="mb-0 ps-3">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
                @endif
                <form action="{{ route('subscription-payments.store', $company) }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label">Monto <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" min="0" name="amount" class="form-control no-uppercase" value="{{ old('amount', $subscription->plan->price) }}" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Método <span class="text-danger">*</span></label>
                            <select name="method" class="form-select" required>
                                @foreach(config('saas.payment_methods') as $key => $label)
                                    <option value="{{ $key }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Referencia</label>
                            <input type="text" name="reference" class="form-control no-uppercase" value="{{ old('reference') }}" placeholder="N° comprobante">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Fecha de pago</label>
                            <input type="date" name="paid_at" class="form-control no-uppercase" value="{{ old('paid_at', now()->toDateString()) }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Comprobante (imagen)</label>
                            <input type="file" name="proof" accept="image/*" class="form-control no-uppercase">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Notas</label>
                            <input type="text" name="notes" class="form-control" value="{{ old('notes') }}">
                        </div>
                        <div class="col-12">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="confirm_now" value="1" id="confirm_now" checked>
                                <label class="form-check-label" for="confirm_now">Confirmar de inmediato y renovar la suscripción</label>
                            </div>
                        </div>
                    </div>
                    <div class="mt-3">
                        <button class="btn btn-primary" type="submit"><i class="bi bi-plus-circle"></i> Registrar pago</button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Historial de pagos --}}
        <div class="card border-0 shadow-sm">
            <div class="card-body p-0">
                <div class="p-3"><h6 class="fw-bold mb-0"><i class="bi bi-receipt"></i> Historial de pagos</h6></div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-3">Fecha</th>
                                <th class="text-end">Monto</th>
                                <th>Método</th>
                                <th>Referencia</th>
                                <th>Periodo</th>
                                <th class="text-center">Estado</th>
                                <th class="text-end pe-3">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($payments as $p)
                                <tr>
                                    <td class="ps-3">{{ optional($p->paid_at)->format('d/m/Y') }}</td>
                                    <td class="text-end fw-semibold">{{ number_format($p->amount, 2) }} {{ $p->currency }}</td>
                                    <td>{{ config('saas.payment_methods')[$p->method] ?? $p->method }}</td>
                                    <td>
                                        {{ $p->reference ?: '—' }}
                                        @if($p->proof)<a href="{{ asset('storage/'.$p->proof) }}" target="_blank" class="ms-1"><i class="bi bi-paperclip"></i></a>@endif
                                    </td>
                                    <td class="small">{{ $p->period_start ? $p->period_start->format('d/m/y').' → '.$p->period_end->format('d/m/y') : '—' }}</td>
                                    <td class="text-center">
                                        <span class="badge {{ ['pending'=>'bg-warning text-dark','confirmed'=>'bg-success','rejected'=>'bg-danger'][$p->status] ?? 'bg-secondary' }}">
                                            {{ ['pending'=>'Pendiente','confirmed'=>'Confirmado','rejected'=>'Rechazado'][$p->status] ?? $p->status }}
                                        </span>
                                    </td>
                                    <td class="text-end pe-3">
                                        @if($p->status === 'pending')
                                            <form action="{{ route('subscription-payments.confirm', $p) }}" method="POST" class="d-inline">
                                                @csrf
                                                <button class="btn btn-sm btn-outline-success" title="Confirmar"><i class="bi bi-check-lg"></i></button>
                                            </form>
                                            <form action="{{ route('subscription-payments.reject', $p) }}" method="POST" class="d-inline" onsubmit="return confirm('¿Rechazar este pago?')">
                                                @csrf
                                                <button class="btn btn-sm btn-outline-danger" title="Rechazar"><i class="bi bi-x-lg"></i></button>
                                            </form>
                                        @else
                                            <span class="text-muted small">—</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="7" class="text-center py-4 text-muted">Sin pagos registrados.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif
</div>
@endsection
