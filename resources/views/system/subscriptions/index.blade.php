@extends('layouts.app')

@section('title', 'Suscripciones')

@section('page')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h1 class="mb-1"><i class="bi bi-card-checklist"></i> Suscripciones</h1>
            <p class="text-muted mb-0">Estado de la suscripción de cada empresa.</p>
        </div>
        <a href="{{ route('plans.index') }}" class="btn btn-outline-primary">
            <i class="bi bi-box-seam"></i> Gestionar planes
        </a>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3">Empresa</th>
                            <th>Plan</th>
                            <th class="text-center">Estado</th>
                            <th class="text-center">Vence</th>
                            <th class="text-end pe-3">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($companies as $company)
                            @php
                                $sub = $company->subscriptions->first();
                                $status = $sub ? $sub->computedStatus() : 'none';
                                $end = $sub?->effectiveEndsAt();
                            @endphp
                            <tr>
                                <td class="ps-3"><div class="fw-semibold">{{ $company->name }}</div></td>
                                <td>{{ $sub?->plan?->name ?? '—' }}</td>
                                <td class="text-center">@include('system.subscriptions._status', ['status' => $status])</td>
                                <td class="text-center">{{ $end ? $end->format('d/m/Y') : '—' }}</td>
                                <td class="text-end pe-3">
                                    <a href="{{ route('subscriptions.show', $company) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-eye"></i> Ver</a>
                                    <a href="{{ route('subscriptions.edit', $company) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-arrow-repeat"></i> Plan</a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center py-5 text-muted">No hay empresas registradas.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer bg-white">{{ $companies->links() }}</div>
    </div>
</div>
@endsection
