@extends('layouts.app')

@section('title', 'Cuenta por pagar · ' . $payable->supplierDisplay())

@section('page')
@php
    $user = auth()->user();
    $co = $user->getCurrentCompany();
    $can = fn ($p) => $user->is_super_admin || ($co && $user->hasPermissionInCompany($p, $co));
@endphp
<div class="container-fluid" style="max-width: 1000px;">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div>
            <h1 class="mb-1"><i class="bi bi-credit-card"></i> {{ $payable->supplierDisplay() }}</h1>
            <p class="text-muted mb-0">
                <span class="badge {{ $payable->stateBadge() }}">{{ $payable->stateLabel() }}</span>
                @if($payable->order) · orden <a href="{{ route('purchase-orders.show', $payable->order) }}">{{ $payable->order->entry_number }}</a>@endif
                · vence el {{ $payable->due_date->format('d/m/Y') }}
            </p>
        </div>
        <a href="{{ route('payables.index') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Cuentas por pagar</a>
    </div>

    @if($errors->any())
        <div class="alert alert-danger"><ul class="mb-0 ps-3">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
    @endif

    <div class="row g-3">
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm mb-3"><div class="card-body">
                <div class="d-flex justify-content-between"><span class="text-muted">Monto de la orden</span><span>{{ $setting->money($payable->amount) }}</span></div>
                <div class="d-flex justify-content-between"><span class="text-muted">Pagado</span><span>− {{ $setting->money($payable->paid_amount) }}</span></div>
                @if((float) $payable->adjusted_amount > 0)
                    <div class="d-flex justify-content-between"><span class="text-muted">Notas de crédito (devoluciones)</span><span>− {{ $setting->money($payable->adjusted_amount) }}</span></div>
                @endif
                <div class="d-flex justify-content-between fw-bold fs-5 border-top pt-2 mt-2"><span>Saldo</span><span>{{ $setting->money($payable->balance()) }}</span></div>
            </div></div>

            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-white border-0 pt-3"><h6 class="fw-bold mb-0">Pagos</h6></div>
                <ul class="list-group list-group-flush">
                    @forelse($payable->payments as $pay)
                        <li class="list-group-item d-flex justify-content-between align-items-center {{ $pay->trashed() ? 'text-muted' : '' }}">
                            <span>
                                <strong>{{ $pay->receipt_number }}</strong>
                                <span class="small text-muted">· {{ $pay->paid_at->format('d/m/Y H:i') }} · {{ \App\Models\PaymentMethod::label($pay->payment_method) }}{{ $pay->session ? ' · caja ' . $pay->session->register?->name : '' }}</span>
                                @if($pay->trashed())<span class="badge bg-secondary">Anulado</span>@endif
                                @if($pay->notes)<div class="small text-muted">{{ $pay->notes }}</div>@endif
                            </span>
                            <span class="d-flex align-items-center gap-2">
                                <span class="fw-semibold {{ $pay->trashed() ? 'text-decoration-line-through' : '' }}">{{ number_format($pay->amount, 2) }}</span>
                                @if(!$pay->trashed() && $can('payables.edit'))
                                    <form action="{{ route('payables.payments.void', $pay) }}" method="POST" onsubmit="return confirm('¿Anular el pago {{ $pay->receipt_number }}?')">
                                        @csrf
                                        <button class="btn btn-sm btn-outline-danger" title="Anular"><i class="bi bi-x"></i></button>
                                    </form>
                                @endif
                            </span>
                        </li>
                    @empty
                        <li class="list-group-item text-muted small">Sin pagos todavía.</li>
                    @endforelse
                </ul>
            </div>

            @if($returns->isNotEmpty())
                <div class="card border-0 shadow-sm"><div class="card-body small">
                    <div class="fw-semibold mb-1">Devoluciones descontadas</div>
                    @foreach($returns as $r)
                        <div><a href="{{ route('purchase-returns.show', $r) }}">{{ $r->return_number }}</a> · {{ number_format($r->applied_to_payable, 2) }}</div>
                    @endforeach
                </div></div>
            @endif
        </div>

        <div class="col-lg-5">
            @if($payable->status === 'pending' && $can('payables.pay'))
                <div class="card border-0 shadow-sm" style="position: sticky; top: .75rem;"><div class="card-body">
                    <h6 class="fw-bold"><i class="bi bi-cash-stack text-primary"></i> Registrar pago</h6>
                    <form action="{{ route('payables.pay', $payable) }}" method="POST">
                        @csrf
                        <label class="form-label small mb-1" for="p-amount">Monto</label>
                        <div class="input-group mb-2">
                            <span class="input-group-text">{{ $setting->currency_symbol }}</span>
                            <input type="number" name="amount" id="p-amount" class="form-control form-control-lg text-end no-uppercase" min="0.01" step="0.01"
                                   max="{{ $payable->balance() }}" required value="{{ old('amount', number_format($payable->balance(), 2, '.', '')) }}">
                        </div>
                        <label class="form-label small mb-1" for="p-method">Forma de pago</label>
                        <select name="payment_method" id="p-method" class="form-select form-select-sm mb-2">
                            @foreach($paymentMethods as $code => $label)<option value="{{ $code }}" @selected(old('payment_method') === $code)>{{ $label }}</option>@endforeach
                        </select>
                        <label class="form-label small mb-1" for="p-session">Sale de la caja</label>
                        <select name="cash_session_id" id="p-session" class="form-select form-select-sm mb-2">
                            <option value="">No, solo registrar el gasto</option>
                            @foreach($openSessions as $s)<option value="{{ $s->id }}" @selected((string) old('cash_session_id') === (string) $s->id)>{{ $s->register?->name ?? 'Caja' }}</option>@endforeach
                        </select>
                        <label class="form-label small mb-1" for="p-notes">Nota</label>
                        <input type="text" name="notes" id="p-notes" class="form-control form-control-sm" maxlength="255" value="{{ old('notes') }}" placeholder="Ej. transferencia N.º">
                        <button class="btn btn-primary w-100 mt-3"><i class="bi bi-check-lg"></i> Registrar pago</button>
                    </form>
                    <p class="small text-muted mt-2 mb-0">Se registra como gasto «Pago a proveedores».</p>
                </div></div>
            @endif
        </div>
    </div>
</div>
@endsection
