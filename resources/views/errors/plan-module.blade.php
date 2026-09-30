@extends('layouts.app')

@section('title', 'Módulo no disponible')

@section('page')
<div class="container-fluid" style="max-width: 640px;">
    <div class="card border-0 shadow-sm mt-4">
        <div class="card-body text-center p-5">
            <div class="mb-3"><i class="bi bi-lock-fill text-warning" style="font-size: 3rem;"></i></div>
            <h1 class="h4 fw-bold mb-2">Módulo no incluido en tu plan</h1>
            <p class="text-muted mb-4">
                El módulo <strong>{{ $module ?? '' }}</strong> no está incluido en el plan actual de tu empresa.
                Contacta al administrador para ampliar tu plan.
            </p>
            <a href="{{ route('dashboard') }}" class="btn btn-primary"><i class="bi bi-house"></i> Volver al inicio</a>
        </div>
    </div>
</div>
@endsection
