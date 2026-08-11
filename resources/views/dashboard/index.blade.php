@extends('layouts.app')

@section('title', 'Dashboard')

@section('page')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-start mb-4">
        <div>
            <h4 class="fw-bold mb-1">Dashboard</h4>
            <p class="text-muted mb-0">Resumen general del sistema</p>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-xl-3 col-md-6">
            <div class="kpi-card">
                <div class="kpi-body">
                    <div>
                        <div class="kpi-value">{{ $totalUsers }}</div>
                        <div class="kpi-label">Usuarios</div>
                        <div class="kpi-trend text-muted"><i class="bi bi-people"></i> Registrados</div>
                    </div>
                    <div class="kpi-icon" style="background: #7c4dff;">
                        <i class="bi bi-person-gear"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="kpi-card">
                <div class="kpi-body">
                    <div>
                        <div class="kpi-value">{{ $totalPersonal }}</div>
                        <div class="kpi-label">Personal</div>
                        <div class="kpi-trend text-muted"><i class="bi bi-person-vcard"></i> Empleados</div>
                    </div>
                    <div class="kpi-icon" style="background: #ff6d00;">
                        <i class="bi bi-person-badge"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="kpi-card">
                <div class="kpi-body">
                    <div>
                        <div class="kpi-value">{{ $totalBranches }}</div>
                        <div class="kpi-label">Sucursales</div>
                        <div class="kpi-trend text-muted"><i class="bi bi-diagram-2"></i> Activas</div>
                    </div>
                    <div class="kpi-icon" style="background: #00c853;">
                        <i class="bi bi-building"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="kpi-card">
                <div class="kpi-body">
                    <div>
                        <div class="kpi-value">{{ $totalCajas }}</div>
                        <div class="kpi-label">Cajas</div>
                        <div class="kpi-trend text-muted"><i class="bi bi-safe"></i> Registradas</div>
                    </div>
                    <div class="kpi-icon" style="background: #0288d1;">
                        <i class="bi bi-safe2"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body text-center py-5">
            <i class="bi bi-grid-1x2 text-muted" style="font-size: 3rem;"></i>
            <h5 class="mt-3 fw-semibold">Bienvenido al sistema base</h5>
            <p class="text-muted mb-0">Este es el proyecto base. Agrega los módulos de tu nuevo proyecto aquí.</p>
        </div>
    </div>
</div>

@push('styles')
<style>
    .kpi-card {
        background: #fff;
        border-radius: 10px;
        padding: 20px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.06);
        border: 0;
    }
    .kpi-body {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
    }
    .kpi-value {
        font-size: 1.8rem;
        font-weight: 700;
        line-height: 1;
        margin-bottom: 4px;
    }
    .kpi-label {
        font-size: 0.82rem;
        color: #777;
        margin-bottom: 6px;
    }
    .kpi-trend {
        font-size: 0.75rem;
    }
    .kpi-icon {
        width: 46px;
        height: 46px;
        border-radius: 12px;
        display: grid;
        place-items: center;
        color: #fff;
        font-size: 1.2rem;
        flex-shrink: 0;
    }
</style>
@endpush
@endsection
