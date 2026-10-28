@extends('layouts.app')

@section('title', 'Estado de cuenta · ' . $client->full_name)

@section('page')
@php
    $user = auth()->user();
    $co = $user->getCurrentCompany();
    $can = fn ($p) => $user->is_super_admin || ($co && $user->hasPermissionInCompany($p, $co));
@endphp
<div class="container-fluid" style="max-width: 1100px;">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div>
            <h1 class="mb-1"><i class="bi bi-wallet2"></i> Estado de cuenta</h1>
            <p class="text-muted mb-0"><a href="{{ route('clients.show', $client) }}" class="fw-semibold">{{ $client->full_name }}</a>
                @if($client->phone) · {{ $client->phone }}@endif</p>
        </div>
        <a href="{{ route('receivables.index') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Cuentas por cobrar</a>
    </div>

    @if($errors->any())
        <div class="alert alert-danger"><ul class="mb-0 ps-3">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
    @endif

    <div class="row g-3 mb-3">
        <div class="col-sm-4"><div class="card border-0 shadow-sm h-100"><div class="card-body">
            <div class="text-muted small">Debe</div>
            <div class="fs-3 fw-bold {{ $summary['overdue'] ? 'text-danger' : '' }}">{{ $setting->money($summary['debt']) }}</div>
            @if($summary['overdue'])<div class="small text-danger"><i class="bi bi-exclamation-triangle"></i> Tiene cuentas vencidas</div>@endif
        </div></div></div>
        <div class="col-sm-4"><div class="card border-0 shadow-sm h-100"><div class="card-body">
            <div class="text-muted small">Límite de crédito</div>
            <div class="fs-4 fw-bold">{{ $summary['enabled'] ? $setting->money($summary['limit']) : 'Sin crédito' }}</div>
            @if($can('clients.edit'))<a href="{{ route('clients.edit', $client) }}" class="small">Cambiar en la ficha del cliente</a>@endif
        </div></div></div>
        <div class="col-sm-4"><div class="card border-0 shadow-sm h-100"><div class="card-body">
            <div class="text-muted small">Disponible para fiar</div>
            <div class="fs-4 fw-bold text-success">{{ $summary['enabled'] ? $setting->money($summary['available']) : '—' }}</div>
        </div></div></div>
    </div>

    <div class="row g-3">
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-white border-0 pt-3"><h6 class="fw-bold mb-0">Cuentas pendientes</h6></div>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead class="table-light"><tr><th class="ps-3">Venta</th><th class="text-end">Monto</th><th class="text-end">Abonado</th><th class="text-end">Saldo</th><th>Vence</th></tr></thead>
                        <tbody>
                            @forelse($pending as $r)
                                <tr>
                                    <td class="ps-3">@if($r->sale)<a href="{{ route('sales.show', $r->sale) }}">{{ $r->sale->sale_number }}</a> <span class="small text-muted">{{ optional($r->sale->sale_date)->format('d/m/Y') }}</span>@endif</td>
                                    <td class="text-end">{{ number_format($r->amount, 2) }}</td>
                                    <td class="text-end">{{ number_format((float) $r->paid_amount + (float) $r->adjusted_amount, 2) }}</td>
                                    <td class="text-end fw-semibold">{{ number_format($r->balance(), 2) }}</td>
                                    <td class="small">{{ $r->due_date->format('d/m/Y') }} <span class="badge {{ $r->stateBadge() }}">{{ $r->stateLabel() }}</span></td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-center text-muted py-4">No debe nada. 🎉</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-white border-0 pt-3"><h6 class="fw-bold mb-0">Recibos</h6></div>
                <ul class="list-group list-group-flush">
                    @forelse($receipts as $rc)
                        <li class="list-group-item d-flex justify-content-between align-items-center {{ $rc->deleted_at ? 'text-muted' : '' }}">
                            <span>
                                <a href="{{ route('receivables.receipt', $rc->receipt_number) }}" class="fw-semibold">{{ $rc->receipt_number }}</a>
                                <span class="small text-muted">· {{ \Illuminate\Support\Carbon::parse($rc->paid_at)->format('d/m/Y H:i') }} · {{ \App\Models\PaymentMethod::label($rc->payment_method) }}</span>
                                @if($rc->deleted_at)<span class="badge bg-secondary">Anulado</span>@endif
                            </span>
                            <span class="fw-semibold {{ $rc->deleted_at ? 'text-decoration-line-through' : '' }}">{{ $setting->money($rc->amount) }}</span>
                        </li>
                    @empty
                        <li class="list-group-item text-muted small">Sin cobros registrados.</li>
                    @endforelse
                </ul>
            </div>

            @if($history->isNotEmpty())
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-0 pt-3"><h6 class="fw-bold mb-0">Cuentas cerradas</h6></div>
                    <ul class="list-group list-group-flush small">
                        @foreach($history as $r)
                            <li class="list-group-item d-flex justify-content-between">
                                <span>@if($r->sale)<a href="{{ route('sales.show', $r->sale) }}">{{ $r->sale->sale_number }}</a>@endif · {{ number_format($r->amount, 2) }}</span>
                                <span class="badge {{ $r->stateBadge() }}">{{ $r->stateLabel() }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>

        <div class="col-lg-5">
            @if($pending->isNotEmpty() && $can('receivables.collect'))
                <div class="card border-0 shadow-sm" style="position: sticky; top: .75rem;">
                    <div class="card-body">
                        <h6 class="fw-bold"><i class="bi bi-cash-coin text-success"></i> Registrar cobro</h6>
                        <form action="{{ route('receivables.collect', $client) }}" method="POST" id="collect-form">
                            @csrf
                            <label class="form-label small mb-1" for="c-amount">Monto</label>
                            <div class="input-group mb-1">
                                <span class="input-group-text">{{ $setting->currency_symbol }}</span>
                                <input type="number" name="amount" id="c-amount" class="form-control form-control-lg text-end no-uppercase" min="0.01" step="0.01"
                                       max="{{ $summary['debt'] }}" required value="{{ old('amount', number_format($summary['debt'], 2, '.', '')) }}">
                            </div>
                            <div class="d-flex gap-1 mb-2">
                                <button type="button" class="btn btn-light border btn-sm py-0" data-amount="{{ $summary['debt'] }}">Todo</button>
                                @if($pending->count() > 1)<button type="button" class="btn btn-light border btn-sm py-0" data-amount="{{ $pending->first()->balance() }}">La más antigua</button>@endif
                            </div>

                            <label class="form-label small mb-1" for="c-target">Aplicar a</label>
                            <select name="receivable_id" id="c-target" class="form-select form-select-sm mb-2">
                                <option value="">Las más antiguas primero</option>
                                @foreach($pending as $r)<option value="{{ $r->id }}" @selected((string) old('receivable_id') === (string) $r->id)>{{ $r->sale?->sale_number }} · saldo {{ number_format($r->balance(), 2) }}</option>@endforeach
                            </select>

                            <label class="form-label small mb-1" for="c-method">Forma de pago</label>
                            <select name="payment_method" id="c-method" class="form-select form-select-sm mb-2">
                                @foreach($paymentMethods as $code => $label)<option value="{{ $code }}" @selected(old('payment_method') === $code)>{{ $label }}</option>@endforeach
                            </select>

                            <label class="form-label small mb-1" for="c-session">Caja</label>
                            <select name="cash_session_id" id="c-session" class="form-select form-select-sm mb-1">
                                @if(!$setting->require_cash_session)<option value="">No registrar en caja</option>@endif
                                @foreach($openSessions as $s)<option value="{{ $s->id }}" @selected((string) old('cash_session_id', $openSessions->count() === 1 ? $s->id : '') === (string) $s->id)>{{ $s->register?->name ?? 'Caja' }}</option>@endforeach
                            </select>
                            @if($openSessions->isEmpty())<div class="small {{ $setting->require_cash_session ? 'text-danger' : 'text-muted' }}">No hay cajas abiertas. <a href="{{ route('cash-sessions.create') }}">Abrir caja</a></div>@endif

                            <label class="form-label small mt-2 mb-1" for="c-notes">Nota</label>
                            <input type="text" name="notes" id="c-notes" class="form-control form-control-sm" maxlength="255" value="{{ old('notes') }}">

                            <button class="btn btn-success w-100 mt-3"><i class="bi bi-check-lg"></i> Cobrar y emitir recibo</button>
                        </form>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    document.querySelectorAll('[data-amount]').forEach(function (b) {
        b.addEventListener('click', function () { document.getElementById('c-amount').value = parseFloat(b.dataset.amount).toFixed(2); });
    });
    document.getElementById('collect-form')?.addEventListener('submit', function (e) { e.submitter && (e.submitter.disabled = true); });
</script>
@endpush
