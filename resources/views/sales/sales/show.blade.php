@extends('layouts.app')

@section('title', 'Venta ' . $sale->sale_number)

@section('page')
<div class="container-fluid" style="max-width: 820px;">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h1 class="mb-1"><i class="bi bi-receipt"></i> Venta {{ $sale->sale_number }}</h1>
            <p class="text-muted mb-0">
                <span class="badge {{ $sale->status === 'completada' ? 'bg-success' : 'bg-danger' }}">{{ ucfirst($sale->status) }}</span>
                · {{ optional($sale->sale_date)->format('d/m/Y H:i') }}
            </p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('sales.index') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Volver</a>
            @if($sale->status === 'completada')
                <form action="{{ route('sales.void', $sale) }}" method="POST" onsubmit="return confirm('¿Anular esta venta? Se revertirá su ingreso de caja si la sesión sigue abierta, se repondrá el stock de los productos y se ajustarán las comisiones.')">
                    @csrf
                    <button class="btn btn-outline-danger"><i class="bi bi-x-circle"></i> Anular</button>
                </form>
            @endif
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-md-3"><div class="card border-0 shadow-sm h-100"><div class="card-body">
            <div class="text-muted small">Cliente</div><div class="fw-semibold">{{ $sale->clientDisplay() }}</div>
        </div></div></div>
        <div class="col-md-3"><div class="card border-0 shadow-sm h-100"><div class="card-body">
            <div class="text-muted small">Profesional principal</div><div class="fw-semibold">{{ $sale->specialist?->full_name ?? '—' }}</div>
        </div></div></div>
        <div class="col-md-3"><div class="card border-0 shadow-sm h-100"><div class="card-body">
            <div class="text-muted small">Método de pago</div><div class="fw-semibold">{{ \App\Models\PaymentMethod::label($sale->payment_method) }}</div>
        </div></div></div>
        <div class="col-md-3"><div class="card border-0 shadow-sm h-100"><div class="card-body">
            <div class="text-muted small">Caja</div><div class="fw-semibold">{{ $sale->session?->register?->name ?? '—' }}</div>
        </div></div></div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead class="table-light">
                        <tr><th class="ps-3">Concepto</th><th>Atendió</th><th class="text-center">Cant.</th><th class="text-end">Precio</th><th class="text-end">Desc.</th><th class="text-end pe-3">Total</th></tr>
                    </thead>
                    <tbody>
                        @foreach($sale->items as $item)
                            <tr>
                                <td class="ps-3">
                                    {{ $item->service?->name ?? $item->product?->name ?? $item->description }}
                                    @if($item->isProduct())<span class="badge bg-warning-subtle text-warning-emphasis">Producto</span>@endif
                                </td>
                                <td class="small text-muted">{{ $item->specialist?->full_name ?? '—' }}</td>
                                <td class="text-center">{{ rtrim(rtrim(number_format($item->quantity, 2), '0'), '.') }}</td>
                                <td class="text-end">{{ number_format($item->unit_price, 2) }}</td>
                                <td class="text-end">{{ number_format($item->discount, 2) }}</td>
                                <td class="text-end pe-3">{{ number_format($item->total, 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr><td colspan="5" class="text-end text-muted">Subtotal</td><td class="text-end pe-3">{{ number_format($sale->subtotal, 2) }}</td></tr>
                        <tr><td colspan="5" class="text-end text-muted">Descuento</td><td class="text-end pe-3">-{{ number_format($sale->discount, 2) }}</td></tr>
                        @php $setting = \App\Models\CompanySetting::current($sale->company_id); @endphp
                        @if((float) $sale->tax > 0)
                            <tr><td colspan="5" class="text-end text-muted">{{ $setting->tax_label }} ({{ rtrim(rtrim(number_format($setting->tax_rate, 2), "0"), ".") }}%){{ $setting->tax_included ? " incluido" : "" }}</td><td class="text-end pe-3">{{ number_format($sale->tax, 2) }}</td></tr>
                        @endif
                        <tr><td colspan="5" class="text-end fw-bold fs-5">Total</td><td class="text-end pe-3 fw-bold fs-5">{{ $setting->money($sale->total) }}</td></tr>
                        @if((float) $sale->tip > 0)
                            <tr>
                                <td colspan="5" class="text-end text-muted">
                                    <i class="bi bi-heart"></i> Propina para {{ $sale->specialist?->full_name ?? 'el profesional' }}
                                    @if($sale->tipSettlement)<span class="badge bg-success">Pagada · {{ $sale->tipSettlement->number }}</span>@else<span class="badge bg-warning text-dark">Pendiente</span>@endif
                                </td>
                                <td class="text-end pe-3">{{ number_format($sale->tip, 2) }}</td>
                            </tr>
                        @endif
                    </tfoot>
                </table>
            </div>
        </div>
    </div>

    @if($sale->notes)
        <div class="mt-3 text-muted"><i class="bi bi-sticky"></i> {{ $sale->notes }}</div>
    @endif
</div>
@endsection
