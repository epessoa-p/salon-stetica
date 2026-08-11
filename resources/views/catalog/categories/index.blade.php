@extends('layouts.app')

@section('title', 'Categorías de servicio')

@section('page')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h1 class="mb-1"><i class="bi bi-tags"></i> Categorías de servicio</h1>
            <p class="text-muted mb-0">Agrupa tus servicios por categoría.</p>
        </div>
        <a href="{{ route('service-categories.create') }}" class="btn btn-primary">
            <i class="bi bi-plus-circle"></i> Nueva Categoría
        </a>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3">Categoría</th>
                            <th class="text-center">Servicios</th>
                            @if(auth()->user()->is_super_admin)
                                <th>Empresa</th>
                            @endif
                            <th class="text-center">Estado</th>
                            <th class="text-end pe-3">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($categories as $category)
                            <tr>
                                <td class="ps-3">
                                    <div class="fw-semibold">{{ $category->name }}</div>
                                    <small class="text-muted">{{ $category->description ?: 'Sin descripción' }}</small>
                                </td>
                                <td class="text-center">{{ $category->services_count }}</td>
                                @if(auth()->user()->is_super_admin)
                                    <td>{{ $category->company?->name ?? '-' }}</td>
                                @endif
                                <td class="text-center">
                                    <span class="badge {{ $category->active ? 'bg-success' : 'bg-secondary' }}">{{ $category->active ? 'Activa' : 'Inactiva' }}</span>
                                </td>
                                <td class="text-end pe-3">
                                    <a href="{{ route('service-categories.edit', $category) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
                                    <form action="{{ route('service-categories.destroy', $category) }}" method="POST" class="d-inline" onsubmit="return confirm('¿Eliminar categoría {{ addslashes($category->name) }}?')">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="{{ auth()->user()->is_super_admin ? 5 : 4 }}" class="text-center py-5 text-muted">No hay categorías registradas.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer bg-white">{{ $categories->links() }}</div>
    </div>
</div>
@endsection
