@extends('layouts.app')

@section('title', 'Planes')

@section('page')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h1 class="mb-1"><i class="bi bi-box-seam"></i> Planes de suscripción</h1>
            <p class="text-muted mb-0">Define los planes, precios y límites de uso del sistema.</p>
        </div>
        <a href="{{ route('plans.create') }}" class="btn btn-primary">
            <i class="bi bi-plus-circle"></i> Nuevo Plan
        </a>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3">Plan</th>
                            <th class="text-end">Precio</th>
                            <th class="text-center">Ciclo</th>
                            <th class="text-center">Prueba</th>
                            <th class="text-center">Límites (Suc/Pers/Cajas/Usu)</th>
                            <th class="text-center">Estado</th>
                            <th class="text-end pe-3">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($plans as $plan)
                            <tr>
                                <td class="ps-3">
                                    <div class="fw-semibold">{{ $plan->name }}</div>
                                    <small class="text-muted">{{ $plan->description ?: 'Sin descripción' }}</small>
                                </td>
                                <td class="text-end fw-semibold">{{ number_format($plan->price, 2) }} {{ $plan->currency }}</td>
                                <td class="text-center">{{ $plan->billing_days }} días</td>
                                <td class="text-center">{{ $plan->trial_days }} días</td>
                                <td class="text-center">
                                    <small>
                                        {{ $plan->max_branches ?? '∞' }} /
                                        {{ $plan->max_personal ?? '∞' }} /
                                        {{ $plan->max_cajas ?? '∞' }} /
                                        {{ $plan->max_users ?? '∞' }}
                                    </small>
                                </td>
                                <td class="text-center">
                                    <span class="badge {{ $plan->active ? 'bg-success' : 'bg-secondary' }}">{{ $plan->active ? 'Activo' : 'Inactivo' }}</span>
                                </td>
                                <td class="text-end pe-3">
                                    <a href="{{ route('plans.edit', $plan) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
                                    <form action="{{ route('plans.destroy', $plan) }}" method="POST" class="d-inline" onsubmit="return confirm('¿Eliminar plan {{ addslashes($plan->name) }}?')">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="text-center py-5 text-muted">No hay planes registrados.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer bg-white">{{ $plans->links() }}</div>
    </div>
</div>
@endsection
