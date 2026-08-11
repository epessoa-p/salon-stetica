@extends('layouts.auth')

@section('title', 'Suscripción vencida')

@section('content')
<div class="auth-container">
    <div class="auth-card text-center">
        <div class="logo" style="background: linear-gradient(135deg, #dc3545, #a71d2a);"><i class="bi bi-lock-fill"></i></div>
        <h1 style="font-size: 1.5rem;">Suscripción vencida</h1>
        <p class="text-muted">
            @if($company)
                El acceso de <strong>{{ $company->name }}</strong> está suspendido porque la suscripción venció.
            @else
                Tu suscripción no está activa.
            @endif
        </p>
        <p class="text-muted small">Ponte en contacto con el administrador para renovar tu plan y reactivar el acceso.</p>

        <form action="{{ route('logout') }}" method="POST" class="mt-3">
            @csrf
            <button type="submit" class="btn btn-login"><i class="bi bi-box-arrow-right"></i> Cerrar sesión</button>
        </form>
    </div>
</div>
@endsection
