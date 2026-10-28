@extends('layouts.app')

@section('title', 'Cuentas por pagar')

@section('page')
@php
    $setting = \App\Models\CompanySetting::current();
    $filters = ['pending' => 'Pendientes', 'overdue' => 'Vencidas', 'paid' => 'Pagadas', 'all' => 'Todas'];
@endphp
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div>
            <h1 class="mb-1"><i class="bi bi-credit-card"></i> Cuentas por pagar</h1>
            <p class="text-muted mb-0">Deudas con proveedores por órdenes de compra recibidas a crédito.</p>
        </div>
    </div>

    @if(!empty($noCompany))
        <div class="alert alert-info"><i class="bi bi-info-circle-fill"></i> Las cuentas por pagar se ven dentro de una empresa.</div>
    @else
        <div class="row g-3 mb-3">
            <div class="col-sm-6 col-xl-3"><div class="card border-0 shadow-sm h-100"><div class="card-body">
                <div class="text-muted small">Por pagar</div><div class="fs-4 fw-bold">{{ $setting->money($kpis['balance']) }}</div>
            </div></div></div>
            <div class="col-sm-6 col-xl-3"><div class="card border-0 shadow-sm h-100"><div class="card-body">
                <div class="text-muted small">Vencido</div><div class="fs-4 fw-bold {{ $kpis['overdue'] > 0 ? 'text-danger' : '' }}">{{ $setting->money($kpis['overdue']) }}</div>
            </div></div></div>
            <div class="col-sm-6 col-xl-3"><div class="card border-0 shadow-sm h-100"><div class="card-body">
                <div class="text-muted small">Vence en 7 días</div><div class="fs-4 fw-bold text-warning-emphasis">{{ $setting->money($kpis['week']) }}</div>
            </div></div></div>
            <div class="col-sm-6 col-xl-3"><div class="card border-0 shadow-sm h-100"><div class="card-body">
                <div class="text-muted small">Pagado este mes</div><div class="fs-4 fw-bold">{{ $setting->money($kpis['paid']) }}</div>
            </div></div></div>
        </div>

        <form class="d-flex gap-2 flex-wrap mb-3" method="GET">
            <div class="btn-group btn-group-sm">
                @foreach($filters as $key => $label)
                    <a href="{{ route('payables.index', ['filter' => $key, 'q' => $term ?: null]) }}" class="btn {{ $filter === $key ? 'btn-dark' : 'btn-outline-secondary' }}">{{ $label }}</a>
                @endforeach
            </div>
            <input type="hidden" name="filter" value="{{ $filter }}">
            <input type="search" name="q" value="{{ $term }}" class="form-control form-control-sm" style="max-width: 260px;" placeholder="Buscar proveedor…">
        </form>

        <div class="card border-0 shadow-sm">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light"><tr><th>Proveedor</th><th>Orden</th><th class="text-end">Monto</th><th class="text-end">Saldo</th><th>Vence</th><th>Estado</th><th></th></tr></thead>
                    <tbody>
                        @forelse($list as $p)
                            <tr>
                                <td class="fw-semibold">{{ $p->supplierDisplay() }}</td>
                                <td>@if($p->order)<a href="{{ route('purchase-orders.show', $p->order) }}">{{ $p->order->entry_number }}</a>@endif</td>
                                <td class="text-end">{{ number_format($p->amount, 2) }}</td>
                                <td class="text-end fw-semibold">{{ number_format($p->balance(), 2) }}</td>
                                <td class="small">{{ $p->due_date->format('d/m/Y') }}@if($p->daysOverdue())<div class="text-danger">{{ $p->daysOverdue() }} día(s) de atraso</div>@endif</td>
                                <td><span class="badge {{ $p->stateBadge() }}">{{ $p->stateLabel() }}</span></td>
                                <td class="text-end"><a href="{{ route('payables.show', $p) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-cash-stack"></i> {{ $p->status === 'pending' ? 'Pagar' : 'Ver' }}</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="text-center text-muted py-4">No hay cuentas {{ $filter === 'all' ? '' : mb_strtolower($filters[$filter] ?? '') }}. Se generan al recibir una orden de compra a crédito.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="mt-3">{{ $list->links() }}</div>
    @endif
</div>
@endsection
