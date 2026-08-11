@extends('layouts.app')

@section('title', 'Servicios')

@section('page')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h1 class="mb-1"><i class="bi bi-scissors"></i> Servicios</h1>
            <p class="text-muted mb-0">Catálogo de servicios del salón.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('service-categories.index') }}" class="btn btn-outline-primary"><i class="bi bi-tags"></i> Categorías</a>
            <a href="{{ route('services.create') }}" class="btn btn-primary"><i class="bi bi-plus-circle"></i> Nuevo Servicio</a>
        </div>
    </div>

    <form method="GET" class="mb-3">
        <div class="input-group" style="max-width: 380px;">
            <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
            <input type="text" name="q" value="{{ $search }}" class="form-control no-uppercase" placeholder="Buscar servicio...">
            @if($search)<a href="{{ route('services.index') }}" class="btn btn-outline-secondary"><i class="bi bi-x-lg"></i></a>@endif
            <button class="btn btn-outline-primary" type="submit">Buscar</button>
        </div>
    </form>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3">Servicio</th>
                            <th>Categoría</th>
                            <th class="text-end">Precio</th>
                            <th class="text-center">Duración</th>
                            <th class="text-center">Comisión</th>
                            @if(auth()->user()->is_super_admin)
                                <th>Empresa</th>
                            @endif
                            <th class="text-center">Estado</th>
                            <th class="text-end pe-3">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($services as $service)
                            <tr>
                                <td class="ps-3">
                                    <div class="fw-semibold">{{ $service->name }}</div>
                                    <small class="text-muted">{{ \Illuminate\Support\Str::limit($service->description, 40) ?: '—' }}</small>
                                </td>
                                <td>{{ $service->category?->name ?? '—' }}</td>
                                <td class="text-end fw-semibold">{{ number_format($service->price, 2) }}</td>
                                <td class="text-center">{{ $service->duration_minutes ? $service->duration_minutes.' min' : '—' }}</td>
                                <td class="text-center">{{ $service->commission_percent !== null ? rtrim(rtrim(number_format($service->commission_percent, 2), '0'), '.').'%' : '—' }}</td>
                                @if(auth()->user()->is_super_admin)
                                    <td>{{ $service->company?->name ?? '-' }}</td>
                                @endif
                                <td class="text-center">
                                    <span class="badge {{ $service->active ? 'bg-success' : 'bg-secondary' }}">{{ $service->active ? 'Activo' : 'Inactivo' }}</span>
                                </td>
                                <td class="text-end pe-3">
                                    <a href="{{ route('services.edit', $service) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
                                    <form action="{{ route('services.destroy', $service) }}" method="POST" class="d-inline" onsubmit="return confirm('¿Eliminar servicio {{ addslashes($service->name) }}?')">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="{{ auth()->user()->is_super_admin ? 8 : 7 }}" class="text-center py-5 text-muted">
                                {{ $search ? 'No se encontraron servicios.' : 'No hay servicios registrados.' }}
                            </td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer bg-white">{{ $services->links() }}</div>
    </div>
</div>
@endsection
