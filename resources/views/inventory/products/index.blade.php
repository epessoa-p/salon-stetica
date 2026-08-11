@extends('layouts.app')

@section('title', 'Productos')

@section('page')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h1 class="mb-1"><i class="bi bi-box"></i> Productos</h1>
            <p class="text-muted mb-0">Catálogo de productos del salón.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('stock.index') }}" class="btn btn-outline-primary"><i class="bi bi-boxes"></i> Existencias</a>
            <a href="{{ route('products.create') }}" class="btn btn-primary"><i class="bi bi-plus-circle"></i> Nuevo Producto</a>
        </div>
    </div>

    <form method="GET" class="mb-3">
        <div class="input-group" style="max-width: 380px;">
            <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
            <input type="text" name="q" value="{{ $search }}" class="form-control no-uppercase" placeholder="Buscar por nombre o SKU...">
            @if($search)<a href="{{ route('products.index') }}" class="btn btn-outline-secondary"><i class="bi bi-x-lg"></i></a>@endif
            <button class="btn btn-outline-primary" type="submit">Buscar</button>
        </div>
    </form>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3">Producto</th>
                            <th>Categoría</th>
                            <th>Marca</th>
                            <th class="text-end">Precio</th>
                            <th class="text-center">Stock</th>
                            @if(auth()->user()->is_super_admin)<th>Empresa</th>@endif
                            <th class="text-center">Estado</th>
                            <th class="text-end pe-3">Acciones</th>
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
                                <td>{{ $product->brand?->name ?? '—' }}</td>
                                <td class="text-end fw-semibold">{{ number_format($product->price, 2) }}</td>
                                <td class="text-center">
                                    {{ rtrim(rtrim(number_format($product->current_stock, 2), '0'), '.') }}
                                    @if($product->isLowStock())<span class="badge bg-danger ms-1" title="Stock bajo">bajo</span>@endif
                                </td>
                                @if(auth()->user()->is_super_admin)<td>{{ $product->company?->name ?? '-' }}</td>@endif
                                <td class="text-center"><span class="badge {{ $product->active ? 'bg-success' : 'bg-secondary' }}">{{ $product->active ? 'Activo' : 'Inactivo' }}</span></td>
                                <td class="text-end pe-3">
                                    <a href="{{ route('stock.movement.create', ['product_id' => $product->id]) }}" class="btn btn-sm btn-outline-secondary" title="Movimiento de stock"><i class="bi bi-arrow-down-up"></i></a>
                                    <a href="{{ route('products.edit', $product) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
                                    <form action="{{ route('products.destroy', $product) }}" method="POST" class="d-inline" onsubmit="return confirm('¿Eliminar producto {{ addslashes($product->name) }}?')">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="{{ auth()->user()->is_super_admin ? 8 : 7 }}" class="text-center py-5 text-muted">
                                {{ $search ? 'No se encontraron productos.' : 'No hay productos registrados.' }}
                            </td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer bg-white">{{ $products->links() }}</div>
    </div>
</div>
@endsection
