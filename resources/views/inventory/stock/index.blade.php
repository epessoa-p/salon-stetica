@extends('layouts.app')

@section('title', 'Existencias')

@section('page')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h1 class="mb-1"><i class="bi bi-boxes"></i> Existencias</h1>
            <p class="text-muted mb-0">Stock actual de productos.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('stock.movements') }}" class="btn btn-outline-primary"><i class="bi bi-list-ul"></i> Movimientos</a>
            <a href="{{ route('stock.movement.create') }}" class="btn btn-primary"><i class="bi bi-arrow-down-up"></i> Registrar movimiento</a>
        </div>
    </div>

    @if($noCompany)
        <div class="alert alert-info d-flex align-items-center gap-2"><i class="bi bi-info-circle-fill"></i> El inventario se gestiona dentro de una empresa.</div>
    @else
        <div class="mb-3">
            <a href="{{ route('stock.index') }}" class="btn btn-sm {{ !$low ? 'btn-primary' : 'btn-outline-secondary' }}">Todos</a>
            <a href="{{ route('stock.index', ['low' => 1]) }}" class="btn btn-sm {{ $low ? 'btn-danger' : 'btn-outline-danger' }}">Stock bajo</a>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-3">Producto</th>
                                <th>Categoría</th>
                                <th class="text-center">Stock actual</th>
                                <th class="text-center">Mínimo</th>
                                <th class="text-center">Estado</th>
                                <th class="text-end pe-3"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($products as $product)
                                <tr>
                                    <td class="ps-3">
                                        <div class="fw-semibold">{{ $product->name }}</div>
                                        <small class="text-muted">SKU: {{ $product->sku }}</small>
                                    </td>
                                    <td>{{ $product->category?->name ?? '—' }}</td>
                                    <td class="text-center fw-semibold">{{ rtrim(rtrim(number_format($product->current_stock, 2), '0'), '.') }} {{ $product->unit }}</td>
                                    <td class="text-center">{{ rtrim(rtrim(number_format($product->min_stock, 2), '0'), '.') }}</td>
                                    <td class="text-center">
                                        @if($product->isLowStock())
                                            <span class="badge bg-danger">Stock bajo</span>
                                        @else
                                            <span class="badge bg-success">OK</span>
                                        @endif
                                    </td>
                                    <td class="text-end pe-3">
                                        <a href="{{ route('stock.movement.create', ['product_id' => $product->id]) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-arrow-down-up"></i> Movimiento</a>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="text-center py-5 text-muted">No hay productos.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer bg-white">{{ $products->links() }}</div>
        </div>
    @endif
</div>
@endsection
