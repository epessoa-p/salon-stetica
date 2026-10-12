@extends('layouts.app')

@section('page')
@php $isSuperAdmin = auth()->user()->is_super_admin; @endphp
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1><i class="bi bi-people"></i> Usuarios</h1>
    <a href="{{ route('users.create') }}" class="btn btn-primary">
        <i class="bi bi-plus-circle"></i> Nuevo Usuario
    </a>
</div>

<div class="card">
    <div class="card-body">
        <table class="table table-hover align-middle">
            <thead>
                <tr>
                    <th>Usuario</th>
                    {{-- Convención: la empresa solo la ve el super admin --}}
                    <th>{{ $isSuperAdmin ? 'Empresa y rol' : 'Rol' }}</th>
                    <th>Teléfono</th>
                    <th>Estado</th>
                    <th class="text-end">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @foreach($users as $user)
                    @php
                        // El admin de empresa solo ve el vínculo con su propia empresa.
                        $links = $isSuperAdmin
                            ? $user->companies
                            : $user->companies->where('id', $currentCompany?->id);
                    @endphp
                    <tr>
                        <td>
                            <div class="fw-semibold">{{ $user->name }}</div>
                            <div class="small text-muted">{{ $user->email }}</div>
                        </td>
                        <td>
                            @if($user->is_super_admin)
                                <span class="badge bg-danger">Operador de la plataforma</span>
                            @elseif($links->isEmpty())
                                <span class="text-muted small"><i class="bi bi-exclamation-circle"></i> Sin empresa asignada</span>
                            @else
                                @foreach($links as $company)
                                    <div class="d-flex align-items-center gap-2 {{ !$loop->last ? 'mb-1' : '' }} {{ $company->pivot->active ? '' : 'opacity-50' }}">
                                        @if($isSuperAdmin)<span>{{ $company->name }}</span>@endif
                                        <span class="badge bg-secondary-subtle text-secondary-emphasis">{{ $roles[$company->pivot->role_id] ?? 'Sin rol' }}</span>
                                        @unless($company->pivot->active)<small class="text-muted">(acceso inactivo)</small>@endunless
                                    </div>
                                @endforeach
                            @endif
                        </td>
                        <td>{{ $user->phone ?: '—' }}</td>
                        <td>
                            <span class="badge {{ $user->active ? 'bg-success' : 'bg-danger' }}">
                                {{ $user->active ? 'Activo' : 'Inactivo' }}
                            </span>
                        </td>
                        <td class="text-end text-nowrap">
                            <a href="{{ route('users.edit', $user) }}" class="btn btn-sm btn-outline-secondary">
                                <i class="bi bi-pencil"></i>
                            </a>
                            @if(auth()->user()->id !== $user->id)
                                <form action="{{ route('users.destroy', $user) }}" method="POST" style="display: inline;">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('¿Estás seguro?')">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="d-flex justify-content-center">
            {{ $users->links() }}
        </div>
    </div>
</div>
@endsection
