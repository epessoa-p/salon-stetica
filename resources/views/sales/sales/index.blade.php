@extends('layouts.app')

@section('title', 'Ventas')

@section('page')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h1 class="mb-1"><i class="bi bi-receipt"></i> Ventas</h1>
            <p class="text-muted mb-0">Historial de ventas registradas.</p>
        </div>
        <a href="{{ route('sales.pos') }}" class="btn btn-primary"><i class="bi bi-upc-scan"></i> Punto de Venta</a>
    </div>

    @if($noCompany)
        <div class="alert alert-info d-flex align-items-center gap-2"><i class="bi bi-info-circle-fill"></i> Las ventas se gestionan dentro de una empresa.</div>
    @else
        <div class="card border-0 shadow-sm">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-3">N°</th>
                                <th>Fecha</th>
                                <th>Cliente</th>
                                <th>Pago</th>
                                <th class="text-end">Total</th>
                                <th class="text-center">Estado</th>
                                <th class="text-end pe-3"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($sales as $sale)
                                <tr class="{{ $sale->status === 'anulada' ? 'text-muted' : '' }}">
                                    <td class="ps-3 fw-semibold">{{ $sale->sale_number }}</td>
                                    <td>{{ optional($sale->sale_date)->format('d/m/Y H:i') }}</td>
                                    <td>{{ $sale->clientDisplay() }}</td>
                                    <td>{{ ucfirst($sale->payment_method) }}</td>
                                    <td class="text-end fw-semibold">{{ number_format($sale->total, 2) }}</td>
                                    <td class="text-center">
                                        <span class="badge {{ $sale->status === 'completada' ? 'bg-success' : 'bg-danger' }}">{{ ucfirst($sale->status) }}</span>
                                    </td>
                                    <td class="text-end pe-3">
                                        <a href="{{ route('sales.show', $sale) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-eye"></i> Ver</a>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="7" class="text-center py-5 text-muted">No hay ventas registradas.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer bg-white">{{ $sales->links() }}</div>
        </div>
    @endif
</div>
@endsection
