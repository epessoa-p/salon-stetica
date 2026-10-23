@extends('layouts.app')

@section('title', 'Devoluciones')

@section('page')
@php
    $user = auth()->user();
    $co = $user->getCurrentCompany();
    $can = fn ($p) => $user->is_super_admin || ($co && $user->hasPermissionInCompany($p, $co));
    $setting = \App\Models\CompanySetting::current();
@endphp
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div>
            <h1 class="mb-1"><i class="bi bi-arrow-return-left"></i> Devoluciones</h1>
            <p class="text-muted mb-0">Lo que los clientes devolvieron, parcial o totalmente, y cómo se les reintegró.</p>
        </div>
        @if(empty($noCompany) && $can('returns.create'))
            <form action="{{ route('returns.find') }}" method="GET" class="d-flex gap-2">
                <input type="text" name="sale_number" class="form-control form-control-sm {{ $errors->has('sale_number') ? 'is-invalid' : '' }}" placeholder="N.º de venta (ej. V-000123)" value="{{ old('sale_number') }}" required style="width: 210px;">
                <button class="btn btn-primary btn-sm text-nowrap"><i class="bi bi-plus-lg"></i> Nueva devolución</button>
            </form>
        @endif
    </div>

    @if(!empty($noCompany))
        <div class="alert alert-info"><i class="bi bi-info-circle-fill"></i> Las devoluciones se registran dentro de una empresa.</div>
    @else
        @error('sale_number')<div class="alert alert-warning py-2">{{ $message }}</div>@enderror
        <p class="small text-muted"><i class="bi bi-lightbulb"></i> También puedes registrar una devolución desde el detalle de la venta, con el botón <strong>Devolución</strong>.</p>

        <form class="d-flex gap-2 align-items-end flex-wrap mb-3" method="GET">
            <div><label class="form-label small mb-1">Desde</label><input type="date" name="from" value="{{ $from }}" class="form-control form-control-sm"></div>
            <div><label class="form-label small mb-1">Hasta</label><input type="date" name="to" value="{{ $to }}" class="form-control form-control-sm"></div>
            <button class="btn btn-sm btn-outline-secondary"><i class="bi bi-funnel"></i> Filtrar</button>
        </form>

        <div class="row g-3 mb-3">
            <div class="col-sm-3"><div class="card border-0 shadow-sm h-100"><div class="card-body"><div class="text-muted small">Devoluciones</div><div class="fs-4 fw-bold">{{ $totals['count'] }}</div></div></div></div>
            <div class="col-sm-3"><div class="card border-0 shadow-sm h-100"><div class="card-body"><div class="text-muted small">Total reintegrado</div><div class="fs-4 fw-bold">{{ $setting->money($totals['amount']) }}</div></div></div></div>
            <div class="col-sm-3"><div class="card border-0 shadow-sm h-100"><div class="card-body"><div class="text-muted small"><i class="bi bi-cash-coin"></i> En dinero</div><div class="fs-5 fw-bold">{{ $setting->money($totals['cash']) }}</div></div></div></div>
            <div class="col-sm-3"><div class="card border-0 shadow-sm h-100"><div class="card-body"><div class="text-muted small"><i class="bi bi-wallet2"></i> Como saldo a favor</div><div class="fs-5 fw-bold">{{ $setting->money($totals['credit']) }}</div></div></div></div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light"><tr><th>Número</th><th>Fecha</th><th>Venta</th><th>Cliente</th><th>Motivo</th><th>Reintegro</th><th class="text-end">Total</th></tr></thead>
                    <tbody>
                        @forelse($returns as $r)
                            <tr>
                                <td><a href="{{ route('returns.show', $r) }}" class="fw-semibold text-decoration-none">{{ $r->return_number }}</a></td>
                                <td class="small">{{ $r->return_date->format('d/m/Y H:i') }}</td>
                                <td>@if($r->sale)<a href="{{ route('sales.show', $r->sale) }}">{{ $r->sale->sale_number }}</a>@endif</td>
                                <td>{{ $r->client?->full_name ?? $r->sale?->clientDisplay() }}</td>
                                <td class="small text-muted">{{ \Illuminate\Support\Str::limit($r->reason, 40) }}</td>
                                <td><i class="bi {{ $r->refundIcon() }}"></i> {{ $r->refundLabel() }}</td>
                                <td class="text-end fw-semibold">{{ $setting->money($r->total) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="text-center text-muted py-4">No hay devoluciones en el periodo.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="mt-3">{{ $returns->links() }}</div>
    @endif
</div>
@endsection
