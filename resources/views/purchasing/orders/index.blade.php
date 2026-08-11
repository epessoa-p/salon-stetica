@extends('layouts.app')

@section('title', $onlyReceived ? 'Recepciones' : 'Órdenes de compra')

@section('page')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h1 class="mb-1"><i class="bi bi-file-earmark-plus"></i> {{ $onlyReceived ? 'Recepciones' : 'Órdenes de compra' }}</h1>
            <p class="text-muted mb-0">{{ $onlyReceived ? 'Órdenes ya recibidas en inventario.' : 'Compras a proveedores.' }}</p>
        </div>
        <div class="d-flex gap-2">
            @unless($onlyReceived)
                <a href="{{ route('suppliers.index') }}" class="btn btn-outline-primary"><i class="bi bi-truck"></i> Proveedores</a>
                <a href="{{ route('purchase-orders.create') }}" class="btn btn-primary"><i class="bi bi-plus-circle"></i> Nueva Orden</a>
            @endunless
        </div>
    </div>

    @if($noCompany)
        <div class="alert alert-info d-flex align-items-center gap-2"><i class="bi bi-info-circle-fill"></i> Las compras se gestionan dentro de una empresa.</div>
    @else
        <div class="card border-0 shadow-sm">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-3">N°</th>
                                <th>Fecha</th>
                                <th>Proveedor</th>
                                <th class="text-end">Total</th>
                                <th class="text-center">Estado</th>
                                <th class="text-end pe-3"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($orders as $order)
                                <tr>
                                    <td class="ps-3 fw-semibold">{{ $order->entry_number }}</td>
                                    <td>{{ optional($order->entry_date)->format('d/m/Y') }}</td>
                                    <td>{{ $order->supplierDisplay() }}</td>
                                    <td class="text-end fw-semibold">{{ number_format($order->total, 2) }}</td>
                                    <td class="text-center"><span class="badge {{ $order->statusBadge() }}">{{ $order->statusLabel() }}</span></td>
                                    <td class="text-end pe-3"><a href="{{ route('purchase-orders.show', $order) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-eye"></i> Ver</a></td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="text-center py-5 text-muted">No hay órdenes {{ $onlyReceived ? 'recibidas' : 'registradas' }}.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer bg-white">{{ $orders->links() }}</div>
        </div>
    @endif
</div>
@endsection
