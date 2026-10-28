@extends('layouts.app')

@section('title', 'Cuentas por cobrar')

@section('page')
@php
    $setting = \App\Models\CompanySetting::current();
    $filters = ['pending' => 'Pendientes', 'overdue' => 'Vencidas', 'paid' => 'Pagadas', 'all' => 'Todas'];
@endphp
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div>
            <h1 class="mb-1"><i class="bi bi-wallet2"></i> Cuentas por cobrar</h1>
            <p class="text-muted mb-0">Ventas a crédito pendientes de pago. Se venden desde el punto de venta con «Venta a crédito».</p>
        </div>
    </div>

    @if(!empty($noCompany))
        <div class="alert alert-info"><i class="bi bi-info-circle-fill"></i> Las cuentas por cobrar se ven dentro de una empresa.</div>
    @else
        <div class="row g-3 mb-3">
            <div class="col-sm-6 col-xl-3"><div class="card border-0 shadow-sm h-100"><div class="card-body">
                <div class="text-muted small">Por cobrar</div><div class="fs-4 fw-bold">{{ $setting->money($kpis['balance']) }}</div>
                <div class="small text-muted">{{ $kpis['clients'] }} cliente(s)</div>
            </div></div></div>
            <div class="col-sm-6 col-xl-3"><div class="card border-0 shadow-sm h-100"><div class="card-body">
                <div class="text-muted small">Vencido</div><div class="fs-4 fw-bold {{ $kpis['overdue'] > 0 ? 'text-danger' : '' }}">{{ $setting->money($kpis['overdue']) }}</div>
            </div></div></div>
            <div class="col-sm-6 col-xl-3"><div class="card border-0 shadow-sm h-100"><div class="card-body">
                <div class="text-muted small">Cobrado este mes</div><div class="fs-4 fw-bold text-success">{{ $setting->money($kpis['collected']) }}</div>
            </div></div></div>
            <div class="col-sm-6 col-xl-3"><div class="card border-0 shadow-sm h-100"><div class="card-body">
                <div class="text-muted small mb-1">Antigüedad</div>
                @php $agingTotal = max(0.01, array_sum($aging)); @endphp
                @foreach($aging as $label => $amount)
                    <div class="d-flex justify-content-between small"><span>{{ $label }}</span><span class="{{ $amount > 0 && $label !== 'Por vencer' ? 'text-danger' : '' }}">{{ number_format($amount, 2) }}</span></div>
                @endforeach
            </div></div></div>
        </div>

        <form class="d-flex gap-2 flex-wrap mb-3" method="GET">
            <div class="btn-group btn-group-sm">
                @foreach($filters as $key => $label)
                    <a href="{{ route('receivables.index', ['filter' => $key, 'q' => $term ?: null]) }}" class="btn {{ $filter === $key ? 'btn-dark' : 'btn-outline-secondary' }}">{{ $label }}</a>
                @endforeach
            </div>
            <input type="hidden" name="filter" value="{{ $filter }}">
            <input type="search" name="q" value="{{ $term }}" class="form-control form-control-sm" style="max-width: 260px;" placeholder="Buscar cliente o teléfono…">
        </form>

        <div class="card border-0 shadow-sm">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light"><tr><th>Cliente</th><th>Venta</th><th class="text-end">Monto</th><th class="text-end">Saldo</th><th>Vence</th><th>Estado</th><th></th></tr></thead>
                    <tbody>
                        @forelse($list as $r)
                            <tr>
                                <td>
                                    <a href="{{ route('receivables.client', $r->client_id) }}" class="fw-semibold text-decoration-none">{{ $r->client?->full_name }}</a>
                                    @if($r->client?->phone)<div class="small text-muted">{{ $r->client->phone }}</div>@endif
                                </td>
                                <td>@if($r->sale)<a href="{{ route('sales.show', $r->sale) }}">{{ $r->sale->sale_number }}</a><div class="small text-muted">{{ optional($r->sale->sale_date)->format('d/m/Y') }}</div>@endif</td>
                                <td class="text-end">{{ number_format($r->amount, 2) }}</td>
                                <td class="text-end fw-semibold">{{ number_format($r->balance(), 2) }}</td>
                                <td class="small">{{ $r->due_date->format('d/m/Y') }}@if($r->daysOverdue())<div class="text-danger">{{ $r->daysOverdue() }} día(s) de atraso</div>@endif</td>
                                <td><span class="badge {{ $r->stateBadge() }}">{{ $r->stateLabel() }}</span></td>
                                <td class="text-end"><a href="{{ route('receivables.client', $r->client_id) }}" class="btn btn-sm btn-outline-primary" title="Estado de cuenta y cobro"><i class="bi bi-cash-coin"></i> Cobrar</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="text-center text-muted py-4">No hay cuentas {{ $filter === 'all' ? '' : mb_strtolower($filters[$filter] ?? '') }}.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="mt-3">{{ $list->links() }}</div>
    @endif
</div>
@endsection
