@extends('layouts.app')

@section('title', 'Existencias')

@section('page')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h1 class="mb-1"><i class="bi bi-boxes"></i> Existencias</h1>
            <p class="text-muted mb-0">{{ $warehouse ?? null ? 'Stock en ' . $warehouse->label() : 'Stock de la empresa y de cada almacén.' }}</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('stock.movements') }}" class="btn btn-outline-primary"><i class="bi bi-list-ul"></i> Movimientos</a>
            @if(Route::has('stock-transfers.index'))<a href="{{ route('stock-transfers.index') }}" class="btn btn-outline-primary"><i class="bi bi-truck"></i> Traspasos</a>@endif
            <a href="{{ route('stock.movement.create') }}" class="btn btn-primary"><i class="bi bi-arrow-down-up"></i> Registrar movimiento</a>
        </div>
    </div>

    @if($noCompany)
        <div class="alert alert-info d-flex align-items-center gap-2"><i class="bi bi-info-circle-fill"></i> El inventario se gestiona dentro de una empresa.</div>
    @else
        <div class="mb-3 d-flex flex-wrap gap-2 align-items-center">
            <a href="{{ route('stock.index', array_filter(['warehouse' => $warehouse?->id])) }}" class="btn btn-sm {{ !$low ? 'btn-primary' : 'btn-outline-secondary' }}">Todos</a>
            <a href="{{ route('stock.index', array_filter(['low' => 1, 'warehouse' => $warehouse?->id])) }}" class="btn btn-sm {{ $low ? 'btn-danger' : 'btn-outline-danger' }}">Stock bajo</a>
            @if($warehouses->count() > 1)
                <span class="text-muted small ms-2">Almacén:</span>
                <a href="{{ route('stock.index', array_filter(['low' => $low ? 1 : null])) }}" class="btn btn-sm {{ !$warehouse ? 'btn-primary' : 'btn-outline-secondary' }}">Todos</a>
                @foreach($warehouses as $w)
                    <a href="{{ route('stock.index', array_filter(['warehouse' => $w->id, 'low' => $low ? 1 : null])) }}" class="btn btn-sm {{ $warehouse?->id === $w->id ? 'btn-primary' : 'btn-outline-secondary' }}">{{ $w->label() }}</a>
                @endforeach
            @endif
        </div>
        @php($fmt = fn ($n) => rtrim(rtrim(number_format((float) $n, 2), '0'), '.'))
        {{-- Con varios almacenes y sin filtro: una columna por almacén (hasta 4). --}}
        @php($columns = (!$warehouse && $warehouses->count() > 1 && $warehouses->count() <= 4) ? $warehouses : collect())

        <div class="card border-0 shadow-sm">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-3">Producto</th>
                                <th>Categoría</th>
                                @foreach($columns as $w)<th class="text-center" title="{{ $w->label() }}">{{ $w->branches->first()?->name ?? $w->name }}</th>@endforeach
                                <th class="text-center">{{ $warehouse ? 'En este almacén' : ($columns->isNotEmpty() ? 'Total' : 'Stock actual') }}</th>
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
                                    @foreach($columns as $w)<td class="text-center">{{ $fmt($byWarehouse[$product->id][$w->id] ?? 0) }}</td>@endforeach
                                    <td class="text-center fw-semibold">{{ $fmt($warehouse ? ($byWarehouse[$product->id][$warehouse->id] ?? 0) : $product->current_stock) }} {{ $product->unit }}</td>
                                    <td class="text-center">{{ rtrim(rtrim(number_format($product->min_stock, 2), '0'), '.') }}</td>
                                    <td class="text-center">
                                        @if($product->isLowStock())
                                            <span class="badge bg-danger">Stock bajo</span>
                                        @else
                                            <span class="badge bg-success">OK</span>
                                        @endif
                                    </td>
                                    <td class="text-end pe-3">
                                        <a href="{{ route('stock.movement.create', array_filter(['product_id' => $product->id, 'warehouse' => $warehouse?->id])) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-arrow-down-up"></i> Movimiento</a>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="{{ 6 + $columns->count() }}" class="text-center py-5 text-muted">No hay productos.</td></tr>
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
