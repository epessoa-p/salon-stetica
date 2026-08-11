@extends('layouts.app')

@section('title', 'Cliente')

@section('page')
<div class="container-fluid" style="max-width: 900px;">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h1 class="mb-1"><i class="bi bi-person-circle"></i> {{ $client->full_name }}</h1>
            <p class="text-muted mb-0">
                <span class="badge {{ $client->active ? 'bg-success' : 'bg-secondary' }}">{{ $client->active ? 'Activo' : 'Inactivo' }}</span>
                @if($client->id_number) · Doc: {{ $client->id_number }} @endif
            </p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('clients.index') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Volver</a>
            <a href="{{ route('clients.edit', $client) }}" class="btn btn-primary"><i class="bi bi-pencil"></i> Editar</a>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <ul class="nav nav-tabs px-3 pt-2" role="tablist">
                <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-datos" type="button">Datos</button></li>
                <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-historial" type="button">Historial</button></li>
                <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-obs" type="button">Observaciones</button></li>
            </ul>

            <div class="tab-content p-4">
                {{-- Datos --}}
                <div class="tab-pane fade show active" id="tab-datos">
                    <div class="row g-3">
                        <div class="col-md-6"><div class="text-muted small">Nombre completo</div><div class="fw-semibold">{{ $client->full_name }}</div></div>
                        <div class="col-md-3"><div class="text-muted small">Documento</div><div>{{ $client->id_number ?: '-' }}</div></div>
                        <div class="col-md-3"><div class="text-muted small">Teléfono</div><div>{{ $client->phone ?: '-' }}</div></div>
                        <div class="col-md-4"><div class="text-muted small">Email</div><div>{{ $client->email ?: '-' }}</div></div>
                        <div class="col-md-3"><div class="text-muted small">Nacimiento</div><div>{{ optional($client->birthdate)->format('d/m/Y') ?: '-' }}</div></div>
                        <div class="col-md-2"><div class="text-muted small">Género</div><div>{{ $client->gender ? ucfirst($client->gender) : '-' }}</div></div>
                        <div class="col-md-3"><div class="text-muted small">Cómo nos conoció</div><div>{{ $client->source ?: '-' }}</div></div>
                        <div class="col-12"><div class="text-muted small">Dirección</div><div>{{ $client->address ?: '-' }}</div></div>
                    </div>
                </div>

                {{-- Historial (próximamente) --}}
                <div class="tab-pane fade" id="tab-historial">
                    <div class="text-center text-muted py-5">
                        <i class="bi bi-clock-history" style="font-size: 2.5rem;"></i>
                        <p class="mt-3 mb-0">El historial de servicios y compras estará disponible cuando se activen los módulos de Agenda y Ventas.</p>
                    </div>
                </div>

                {{-- Observaciones --}}
                <div class="tab-pane fade" id="tab-obs">
                    @if($client->notes)
                        <p class="mb-0" style="white-space: pre-line;">{{ $client->notes }}</p>
                    @else
                        <p class="text-muted mb-0">Sin observaciones registradas.</p>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
