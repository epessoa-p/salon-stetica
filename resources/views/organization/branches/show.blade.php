@extends('layouts.app')

@section('title', 'Detalle sucursal')

@section('page')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="mb-1">{{ $branch->name }}</h1>
            <p class="text-muted mb-0">Detalle general de la sucursal.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('branches.edit', $branch) }}" class="btn btn-primary">Editar</a>
            <a href="{{ route('branches.index') }}" class="btn btn-outline-secondary">Volver</a>
        </div>
    </div>
    <div class="row g-4">
        <div class="col-lg-6"><div class="card border-0 shadow-sm"><div class="card-body"><p><strong>Empresa:</strong> {{ $branch->company?->name }}</p><p><strong>Código:</strong> {{ $branch->code ?: '-' }}</p><p><strong>Almacén principal:</strong> {{ $branch->warehouse?->name ?: '-' }}</p><p><strong>Encargado:</strong> {{ $branch->manager_name ?: '-' }}</p><p><strong>Correo:</strong> {{ $branch->email ?: '-' }}</p><p><strong>Teléfono:</strong> {{ $branch->phone ?: '-' }}</p><p><strong>Dirección:</strong> {{ $branch->address ?: '-' }}</p></div></div></div>
        <div class="col-lg-6"><div class="card border-0 shadow-sm"><div class="card-body"><h6 class="fw-bold">Almacén principal</h6><div class="mt-3"><p class="mb-1"><strong>Nombre:</strong> {{ $branch->warehouse?->name ?: '-' }}</p><p class="mb-1"><strong>Código:</strong> {{ $branch->warehouse?->code ?: '-' }}</p><p class="mb-0"><strong>Ubicación:</strong> {{ $branch->warehouse?->location ?: '-' }}</p></div></div></div></div>
    </div>
</div>
@endsection