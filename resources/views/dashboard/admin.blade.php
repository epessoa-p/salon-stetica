@extends('layouts.app')

@section('title', 'Panel global')

@section('page')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-start mb-4 flex-wrap gap-2">
        <div>
            <h4 class="fw-bold mb-1">Panel global</h4>
            <p class="text-muted mb-0">Resumen de la plataforma · {{ now()->translatedFormat('l d \d\e F') }}</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('companies.create') }}" class="btn btn-outline-primary"><i class="bi bi-plus-circle"></i> Nueva empresa</a>
            <a href="{{ route('subscriptions.index') }}" class="btn btn-primary"><i class="bi bi-card-checklist"></i> Suscripciones</a>
        </div>
    </div>

    {{-- KPIs --}}
    <div class="row g-3 mb-4">
        <div class="col-xl-3 col-md-6">
            <div class="kpi-card"><div class="kpi-body">
                <div>
                    <div class="kpi-value">{{ $totalCompanies }}</div>
                    <div class="kpi-label">Empresas</div>
                    <div class="kpi-trend text-muted"><i class="bi bi-check-circle"></i> {{ $activeCompanies }} activas</div>
                </div>
                <div class="kpi-icon" style="background: #7c4dff;"><i class="bi bi-building"></i></div>
            </div></div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="kpi-card"><div class="kpi-body">
                <div>
                    <div class="kpi-value">{{ ($estados['active'] ?? 0) + ($estados['trial'] ?? 0) }}</div>
                    <div class="kpi-label">Suscripciones vigentes</div>
                    <div class="kpi-trend text-muted"><i class="bi bi-hourglass-split"></i> {{ $estados['trial'] ?? 0 }} en prueba</div>
                </div>
                <div class="kpi-icon" style="background: #10b981;"><i class="bi bi-card-checklist"></i></div>
            </div></div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="kpi-card"><div class="kpi-body">
                <div>
                    <div class="kpi-value">{{ number_format($ingresosMes, 2) }}</div>
                    <div class="kpi-label">Cobrado este mes</div>
                    <div class="kpi-trend text-muted"><i class="bi bi-cash-coin"></i> {{ $pagosPendientes }} pago(s) pendiente(s)</div>
                </div>
                <div class="kpi-icon" style="background: #14b8a6;"><i class="bi bi-wallet2"></i></div>
            </div></div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="kpi-card"><div class="kpi-body">
                <div>
                    <div class="kpi-value {{ (($estados['expired'] ?? 0) + ($estados['grace'] ?? 0)) > 0 ? 'text-danger' : '' }}">
                        {{ ($estados['expired'] ?? 0) + ($estados['grace'] ?? 0) }}
                    </div>
                    <div class="kpi-label">Vencidas / en gracia</div>
                    <div class="kpi-trend text-muted"><i class="bi bi-exclamation-triangle"></i> {{ $estados['none'] ?? 0 }} sin plan</div>
                </div>
                <div class="kpi-icon" style="background: #ef4444;"><i class="bi bi-exclamation-octagon"></i></div>
            </div></div>
        </div>
    </div>

    <div class="row g-3">
        {{-- Por vencer --}}
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body p-0">
                    <div class="d-flex justify-content-between align-items-center p-3">
                        <h6 class="fw-bold mb-0"><i class="bi bi-alarm"></i> Suscripciones por vencer (10 días)</h6>
                        <a href="{{ route('subscriptions.index') }}" class="btn btn-sm btn-outline-secondary">Ver todas</a>
                    </div>
                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead class="table-light">
                                <tr><th class="ps-3">Empresa</th><th>Plan</th><th class="text-center">Estado</th><th class="text-center">Vence en</th><th class="text-end pe-3"></th></tr>
                            </thead>
                            <tbody>
                                @forelse($porVencer as $row)
                                    <tr>
                                        <td class="ps-3 fw-semibold">{{ $row['company']->name }}</td>
                                        <td>{{ $row['sub']->plan?->name ?? '—' }}</td>
                                        <td class="text-center">@include('system.subscriptions._status', ['status' => $row['estado']])</td>
                                        <td class="text-center {{ $row['dias'] <= 3 ? 'text-danger fw-semibold' : '' }}">
                                            {{ $row['dias'] < 0 ? 'vencida' : $row['dias'].' día(s)' }}
                                        </td>
                                        <td class="text-end pe-3">
                                            <a href="{{ route('subscriptions.show', $row['company']) }}" class="btn btn-sm btn-outline-primary">Gestionar</a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="5" class="text-center py-4 text-muted">Ninguna suscripción vence en los próximos 10 días.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        {{-- Distribución de estados --}}
        <div class="col-lg-5">
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-body">
                    <h6 class="fw-bold mb-3"><i class="bi bi-pie-chart"></i> Estado de las suscripciones</h6>
                    @php
                        $labels = ['trial' => 'En prueba', 'active' => 'Activas', 'grace' => 'En gracia', 'expired' => 'Vencidas', 'none' => 'Sin plan'];
                        $total = max(1, array_sum($estados));
                    @endphp
                    @foreach($labels as $key => $label)
                        @php $n = $estados[$key] ?? 0; $pct = round($n * 100 / $total); @endphp
                        <div class="mb-2">
                            <div class="d-flex justify-content-between small">
                                <span>{{ $label }}</span>
                                <span class="text-muted">{{ $n }}</span>
                            </div>
                            <div class="progress" style="height: 6px;">
                                <div class="progress-bar {{ ['trial'=>'bg-info','active'=>'bg-success','grace'=>'bg-warning','expired'=>'bg-danger','none'=>'bg-secondary'][$key] }}"
                                     style="width: {{ $pct }}%"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <h6 class="fw-bold mb-3"><i class="bi bi-people"></i> Plataforma</h6>
                    <div class="d-flex justify-content-between py-1"><span class="text-muted">Usuarios</span><span class="fw-semibold">{{ $totalUsers }}</span></div>
                    <div class="d-flex justify-content-between py-1"><span class="text-muted">Personal registrado</span><span class="fw-semibold">{{ $totalPersonal }}</span></div>
                    <div class="d-flex justify-content-between py-1"><span class="text-muted">Suscripciones creadas</span><span class="fw-semibold">{{ $totalSubscriptions }}</span></div>
                </div>
            </div>
        </div>
    </div>
</div>

@push('styles')
<style>
    .kpi-card { background: #fff; border-radius: 10px; padding: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); border: 0; height: 100%; }
    .kpi-body { display: flex; justify-content: space-between; align-items: flex-start; }
    .kpi-value { font-size: 1.8rem; font-weight: 700; line-height: 1; margin-bottom: 4px; }
    .kpi-label { font-size: 0.82rem; color: #777; margin-bottom: 6px; }
    .kpi-trend { font-size: 0.75rem; }
    .kpi-icon { width: 46px; height: 46px; border-radius: 12px; display: grid; place-items: center; color: #fff; font-size: 1.2rem; flex-shrink: 0; }
</style>
@endpush
@endsection
