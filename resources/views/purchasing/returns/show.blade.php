@extends('layouts.app')

@section('title', 'Devolución ' . $return->return_number)

@section('page')
@php $qty = fn ($n) => rtrim(rtrim(number_format((float) $n, 2), '0'), '.'); @endphp
<div class="container-fluid" style="max-width: 860px;">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div>
            <h1 class="mb-1"><i class="bi bi-arrow-return-left"></i> Devolución {{ $return->return_number }}</h1>
            <p class="text-muted mb-0">{{ $return->return_date->format('d/m/Y H:i') }}{{ $return->creator ? ' · ' . $return->creator->name : '' }}
                @if($return->order) · orden <a href="{{ route('purchase-orders.show', $return->order) }}">{{ $return->order->entry_number }}</a>@endif</p>
        </div>
        <a href="{{ route('purchase-returns.index') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Devoluciones</a>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-md-4"><div class="card border-0 shadow-sm h-100"><div class="card-body">
            <div class="text-muted small">Proveedor</div><div class="fw-semibold">{{ $return->provider?->name ?? $return->order?->supplierDisplay() ?? '—' }}</div>
        </div></div></div>
        <div class="col-md-4"><div class="card border-0 shadow-sm h-100"><div class="card-body">
            <div class="text-muted small">Reintegro</div><div class="fw-semibold">{{ $return->refundLabel() }}</div>
            @if($return->cashMovement)<div class="small text-muted">Ingreso registrado · {{ \App\Models\PaymentMethod::label($return->cashMovement->payment_method) }}</div>@endif
        </div></div></div>
        <div class="col-md-4"><div class="card border-0 shadow-sm h-100"><div class="card-body">
            <div class="text-muted small">Total al costo</div><div class="fs-4 fw-bold">{{ $setting->money($return->total) }}</div>
        </div></div></div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead class="table-light"><tr><th class="ps-3">Producto</th><th class="text-center">Cantidad</th><th class="text-end">Costo</th><th class="text-end pe-3">Total</th></tr></thead>
                <tbody>
                    @foreach($return->items as $i)
                        <tr>
                            <td class="ps-3">{{ $i->product?->name ?? 'Producto #' . $i->product_id }}</td>
                            <td class="text-center">{{ $qty($i->quantity) }}</td>
                            <td class="text-end">{{ number_format($i->unit_cost, 2) }}</td>
                            <td class="text-end pe-3">{{ number_format($i->total, 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div class="card border-0 shadow-sm mt-3"><div class="card-body small">
        <div><strong>Motivo:</strong> {{ $return->reason }}</div>
        @if($return->notes)<div class="mt-1"><strong>Notas:</strong> {{ $return->notes }}</div>@endif
    </div></div>
</div>
@endsection
