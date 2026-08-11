@extends('layouts.app')

@section('page')
<h1><i class="bi bi-building"></i> {{ $company->name }}</h1>

<div class="row mt-4">
    <div class="col-md-6">
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">Información</h5>
            </div>
            <div class="card-body">
                <p><strong>RUC:</strong> {{ $company->ruc ?? '-' }}</p>
                <p><strong>Email:</strong> {{ $company->email ?? '-' }}</p>
                <p><strong>Teléfono:</strong> {{ $company->phone ?? '-' }}</p>
                <p><strong>Dirección:</strong> {{ $company->address ?? '-' }}</p>
                <p><strong>Estado:</strong> <span class="badge {{ $company->active ? 'bg-success' : 'bg-danger' }}">{{ $company->active ? 'Activo' : 'Inactivo' }}</span></p>
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Acciones</h5>
            </div>
            <div class="card-body">
                <a href="{{ route('companies.edit', $company) }}" class="btn btn-warning w-100 mb-2">
                    <i class="bi bi-pencil"></i> Editar
                </a>
                <form action="{{ route('companies.destroy', $company) }}" method="POST">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-danger w-100" onclick="return confirm('¿Estás seguro?')">
                        <i class="bi bi-trash"></i> Eliminar
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="card mt-4">
    <div class="card-header">
        <h5 class="mb-0">Usuarios de la Empresa</h5>
    </div>
    <div class="card-body">
        <table class="table">
            <thead>
                <tr>
                    <th>Nombre</th>
                    <th>Email</th>
                    <th>Rol</th>
                </tr>
            </thead>
            <tbody>
                @forelse($users as $user)
                    <tr>
                        <td>{{ $user->name }}</td>
                        <td>{{ $user->email }}</td>
                        <td>{{ $user->pivot->role_id ? \App\Models\Role::find($user->pivot->role_id)->name : '-' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="3" class="text-center text-muted">No hay usuarios en esta empresa</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<a href="{{ route('companies.index') }}" class="btn btn-secondary mt-4">
    <i class="bi bi-arrow-left"></i> Volver
</a>
@endsection
