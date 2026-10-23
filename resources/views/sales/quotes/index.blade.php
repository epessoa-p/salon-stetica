@extends('layouts.app')

@section('title', 'Cotizaciones')

@section('page')
@php
    $user = auth()->user();
    $co = $user->getCurrentCompany();
    $can = fn ($p) => $user->is_super_admin || ($co && $user->hasPermissionInCompany($p, $co));
    $setting = \App\Models\CompanySetting::current();
    $filters = ['pending' => 'Vigentes', 'expired' => 'Vencidas', 'converted' => 'Convertidas', 'void' => 'Anuladas', 'all' => 'Todas'];
@endphp
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div>
            <h1 class="mb-1"><i class="bi bi-file-earmark-text"></i> Cotizaciones</h1>
            <p class="text-muted mb-0">Presupuestos armados en el punto de venta. Se convierten en venta con un clic.</p>
        </div>
        @if(empty($noCompany) && $can('quotes.create') && $can('sales.create'))
            <a href="{{ route('sales.pos') }}" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Nueva cotización</a>
        @endif
    </div>

    @if(!empty($noCompany))
        <div class="alert alert-info"><i class="bi bi-info-circle-fill"></i> Las cotizaciones se usan dentro de una empresa.</div>
    @else
        <div class="row g-3 mb-3">
            <div class="col-sm-4"><div class="card border-0 shadow-sm h-100"><div class="card-body">
                <div class="text-muted small">Vigentes</div>
                <div class="fs-4 fw-bold">{{ $stats['pending'] }}</div>
            </div></div></div>
            <div class="col-sm-4"><div class="card border-0 shadow-sm h-100"><div class="card-body">
                <div class="text-muted small">Monto por cerrar</div>
                <div class="fs-4 fw-bold">{{ $setting->money($stats['pending_amount']) }}</div>
            </div></div></div>
            <div class="col-sm-4"><div class="card border-0 shadow-sm h-100"><div class="card-body">
                <div class="text-muted small">Convertidas en venta (90 días)</div>
                <div class="fs-4 fw-bold">
                    {{ $stats['total90'] ? round(100 * $stats['converted90'] / $stats['total90']) : 0 }} %
                    <span class="fs-6 text-muted fw-normal">{{ $stats['converted90'] }} de {{ $stats['total90'] }}</span>
                </div>
            </div></div></div>
        </div>

        <form class="d-flex gap-2 flex-wrap mb-3" method="GET">
            <div class="btn-group btn-group-sm">
                @foreach($filters as $key => $label)
                    <a href="{{ route('quotes.index', ['filter' => $key, 'q' => $term ?: null]) }}" class="btn {{ $filter === $key ? 'btn-dark' : 'btn-outline-secondary' }}">{{ $label }}</a>
                @endforeach
            </div>
            <input type="hidden" name="filter" value="{{ $filter }}">
            <input type="search" name="q" value="{{ $term }}" class="form-control form-control-sm" style="max-width: 260px;" placeholder="Número o cliente…">
        </form>

        <div class="card border-0 shadow-sm">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr><th>Número</th><th>Fecha</th><th>Cliente</th><th class="text-end">Total</th><th>Vigencia</th><th>Estado</th><th></th></tr>
                    </thead>
                    <tbody>
                        @forelse($quotes as $q)
                            <tr>
                                <td><a href="{{ route('quotes.show', $q) }}" class="fw-semibold text-decoration-none">{{ $q->quote_number }}</a></td>
                                <td class="small">{{ $q->created_at->format('d/m/Y') }}</td>
                                <td>{{ $q->clientDisplay() }}</td>
                                <td class="text-end">{{ $setting->money($q->total) }}</td>
                                <td class="small">{{ $q->valid_until->format('d/m/Y') }}</td>
                                <td>
                                    <span class="badge {{ $q->stateBadge() }}">{{ $q->stateLabel() }}</span>
                                    @if($q->sale)<a href="{{ route('sales.show', $q->sale) }}" class="small ms-1">{{ $q->sale->sale_number }}</a>@endif
                                </td>
                                <td class="text-end text-nowrap">
                                    @if($q->canConvert() && $can('sales.create'))
                                        <a href="{{ route('sales.pos', ['quote' => $q->id]) }}" class="btn btn-sm btn-success" title="Convertir en venta"><i class="bi bi-cart-check"></i></a>
                                    @endif
                                    <a href="{{ route('quotes.show', $q) }}" class="btn btn-sm btn-outline-secondary" title="Ver"><i class="bi bi-eye"></i></a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="text-center text-muted py-4">
                                No hay cotizaciones {{ $filter === 'all' ? '' : mb_strtolower($filters[$filter] ?? '') }}.
                                @if($can('quotes.create') && $can('sales.create'))
                                    <div class="small mt-1">Arma el carrito en el <a href="{{ route('sales.pos') }}">punto de venta</a> y usa <strong>Guardar como cotización</strong>.</div>
                                @endif
                            </td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="mt-3">{{ $quotes->links() }}</div>
    @endif
</div>
@endsection
