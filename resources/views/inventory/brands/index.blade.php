@extends('layouts.app')

@section('title', 'Marcas')

@section('page')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h1 class="mb-1"><i class="bi bi-bookmark"></i> Marcas</h1>
            <p class="text-muted mb-0">Marcas de tus productos.</p>
        </div>
        <a href="{{ route('brands.create') }}" class="btn btn-primary"><i class="bi bi-plus-circle"></i> Nueva Marca</a>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3">Marca</th>
                            <th class="text-center">Productos</th>
                            @if(auth()->user()->is_super_admin)<th>Empresa</th>@endif
                            <th class="text-center">Estado</th>
                            <th class="text-end pe-3">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($brands as $brand)
                            <tr>
                                <td class="ps-3 fw-semibold">{{ $brand->name }}</td>
                                <td class="text-center">{{ $brand->products_count }}</td>
                                @if(auth()->user()->is_super_admin)<td>{{ $brand->company?->name ?? '-' }}</td>@endif
                                <td class="text-center"><span class="badge {{ $brand->active ? 'bg-success' : 'bg-secondary' }}">{{ $brand->active ? 'Activa' : 'Inactiva' }}</span></td>
                                <td class="text-end pe-3">
                                    <a href="{{ route('brands.edit', $brand) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
                                    <form action="{{ route('brands.destroy', $brand) }}" method="POST" class="d-inline" onsubmit="return confirm('¿Eliminar marca {{ addslashes($brand->name) }}?')">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="{{ auth()->user()->is_super_admin ? 5 : 4 }}" class="text-center py-5 text-muted">No hay marcas registradas.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer bg-white">{{ $brands->links() }}</div>
    </div>
</div>
@endsection
