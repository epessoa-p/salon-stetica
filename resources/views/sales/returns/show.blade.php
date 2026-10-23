@extends('layouts.app')

@section('title', 'Devolución ' . $return->return_number)

@section('page')
@php $qty = fn ($n) => rtrim(rtrim(number_format((float) $n, 2), '0'), '.'); @endphp
<div class="container-fluid" style="max-width: 860px;">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div>
            <h1 class="mb-1"><i class="bi bi-arrow-return-left"></i> Devolución {{ $return->return_number }}</h1>
            <p class="text-muted mb-0">{{ $return->return_date->format('d/m/Y H:i') }}{{ $return->creator ? ' · ' . $return->creator->name : '' }}
                · venta <a href="{{ route('sales.show', $return->sale) }}">{{ $return->sale?->sale_number }}</a></p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('returns.index') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Devoluciones</a>
            <a href="{{ route('sales.show', $return->sale) }}" class="btn btn-outline-primary"><i class="bi bi-receipt"></i> Ver venta</a>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-md-4"><div class="card border-0 shadow-sm h-100"><div class="card-body">
            <div class="text-muted small">Cliente</div><div class="fw-semibold">{{ $return->client?->full_name ?? $return->sale?->clientDisplay() }}</div>
        </div></div></div>
        <div class="col-md-4"><div class="card border-0 shadow-sm h-100"><div class="card-body">
            <div class="text-muted small">Reintegro</div>
            <div class="fw-semibold"><i class="bi {{ $return->refundIcon() }}"></i> {{ $return->refundLabel() }}
                @if($return->refund_method === 'cash') · {{ \App\Models\PaymentMethod::label($return->payment_method) }}@endif</div>
            @if($return->session)<div class="small text-muted">Caja {{ $return->session->register?->name }}</div>@endif
        </div></div></div>
        <div class="col-md-4"><div class="card border-0 shadow-sm h-100"><div class="card-body">
            <div class="text-muted small">Total</div><div class="fs-4 fw-bold">{{ $setting->money($return->total) }}</div>
        </div></div></div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead class="table-light"><tr><th class="ps-3">Línea</th><th class="text-center">Cantidad</th><th class="text-center">Stock</th><th class="text-end pe-3">Reintegro</th></tr></thead>
                <tbody>
                    @foreach($return->items as $ri)
                        <tr>
                            <td class="ps-3">{{ $ri->saleItem?->description ?: ($ri->saleItem?->service?->name ?? $ri->saleItem?->product?->name) }}</td>
                            <td class="text-center">{{ $qty($ri->quantity) }}</td>
                            <td class="text-center">{!! $ri->saleItem?->isProduct() ? ($ri->restock ? '<span class="badge bg-success">Repuesto</span>' : '<span class="badge bg-secondary">No repuesto</span>') : '—' !!}</td>
                            <td class="text-end pe-3">{{ number_format($ri->amount, 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div class="card border-0 shadow-sm mt-3"><div class="card-body small">
        <div><strong>Motivo:</strong> {{ $return->reason }}</div>
        @if($return->notes)<div class="mt-1"><strong>Notas:</strong> {{ $return->notes }}</div>@endif
        @if($return->points_removed || $return->points_returned)
            <div class="mt-1"><i class="bi bi-stars text-warning"></i>
                @if($return->points_removed) Se quitaron {{ $return->points_removed }} puntos ganados.@endif
                @if($return->points_returned) Se devolvieron {{ $return->points_returned }} puntos canjeados.@endif
            </div>
        @endif
    </div></div>
</div>
@endsection
