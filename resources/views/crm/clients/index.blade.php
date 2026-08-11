@extends('layouts.app')

@section('title', 'Clientes')

@section('page')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h1 class="mb-1"><i class="bi bi-people-fill"></i> Clientes</h1>
            <p class="text-muted mb-0">Administra las fichas de tus clientes.</p>
        </div>
        <a href="{{ route('clients.create') }}" class="btn btn-primary">
            <i class="bi bi-plus-circle"></i> Nuevo Cliente
        </a>
    </div>

    <form method="GET" class="mb-3">
        <div class="input-group" style="max-width: 420px;">
            <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
            <input type="text" name="q" value="{{ $search }}" class="form-control no-uppercase" placeholder="Buscar por nombre, documento, teléfono o email...">
            @if($search)
                <a href="{{ route('clients.index') }}" class="btn btn-outline-secondary"><i class="bi bi-x-lg"></i></a>
            @endif
            <button class="btn btn-outline-primary" type="submit">Buscar</button>
        </div>
    </form>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3">Cliente</th>
                            <th>Documento</th>
                            <th>Contacto</th>
                            @if(auth()->user()->is_super_admin)
                                <th>Empresa</th>
                            @endif
                            <th class="text-center">Estado</th>
                            <th class="text-end pe-3">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($clients as $client)
                            <tr>
                                <td class="ps-3">
                                    <div class="fw-semibold">{{ $client->full_name }}</div>
                                    <small class="text-muted">{{ $client->email ?: 'Sin email' }}</small>
                                </td>
                                <td>{{ $client->id_number ?: '-' }}</td>
                                <td>{{ $client->phone ?: '-' }}</td>
                                @if(auth()->user()->is_super_admin)
                                    <td>{{ $client->company?->name ?? '-' }}</td>
                                @endif
                                <td class="text-center">
                                    <span class="badge {{ $client->active ? 'bg-success' : 'bg-secondary' }}">{{ $client->active ? 'Activo' : 'Inactivo' }}</span>
                                </td>
                                <td class="text-end pe-3">
                                    <a href="{{ route('clients.show', $client) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-eye"></i></a>
                                    <a href="{{ route('clients.edit', $client) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
                                    <form action="{{ route('clients.destroy', $client) }}" method="POST" class="d-inline" onsubmit="return confirm('¿Eliminar cliente {{ addslashes($client->full_name) }}?')">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="{{ auth()->user()->is_super_admin ? 6 : 5 }}" class="text-center py-5 text-muted">
                                {{ $search ? 'No se encontraron clientes para la búsqueda.' : 'No hay clientes registrados.' }}
                            </td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer bg-white">{{ $clients->links() }}</div>
    </div>
</div>
@endsection
