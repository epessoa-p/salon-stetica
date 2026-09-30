@extends('layouts.app')

@section('title', 'Dashboard')

@section('page')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-start mb-4 flex-wrap gap-2">
        <div>
            <h4 class="fw-bold mb-1">Hola, {{ auth()->user()->personal?->full_name ?? auth()->user()->name }}</h4>
            <p class="text-muted mb-0">Resumen de {{ $company?->name ?? 'tu salón' }} · {{ now()->translatedFormat('l d \d\e F') }}</p>
        </div>
        @if($show['ventas'])
            <a href="{{ route('sales.pos') }}" class="btn btn-primary"><i class="bi bi-upc-scan"></i> Punto de Venta</a>
        @endif
    </div>

    {{-- ===== KPIs ===== --}}
    <div class="row g-3 mb-4">
        @if($show['agenda'])
            <div class="col-xl-3 col-md-6">
                <div class="kpi-card">
                    <div class="kpi-body">
                        <div>
                            <div class="kpi-value">{{ $citasHoy }}</div>
                            <div class="kpi-label">Citas de hoy</div>
                            <div class="kpi-trend text-muted"><i class="bi bi-clock"></i> {{ $citasPendientes }} por atender</div>
                        </div>
                        <div class="kpi-icon" style="background: #6366f1;"><i class="bi bi-calendar-check"></i></div>
                    </div>
                </div>
            </div>
        @endif

        @if($show['ventas'])
            <div class="col-xl-3 col-md-6">
                <div class="kpi-card">
                    <div class="kpi-body">
                        <div>
                            <div class="kpi-value">{{ number_format($ventasHoyTotal, 2) }}</div>
                            <div class="kpi-label">Ventas de hoy</div>
                            <div class="kpi-trend text-muted"><i class="bi bi-receipt"></i> {{ $ventasHoyCount }} venta(s)</div>
                        </div>
                        <div class="kpi-icon" style="background: #14b8a6;"><i class="bi bi-cash-coin"></i></div>
                    </div>
                </div>
            </div>
        @endif

        @if($show['clientes'])
            <div class="col-xl-3 col-md-6">
                <div class="kpi-card">
                    <div class="kpi-body">
                        <div>
                            <div class="kpi-value">{{ $clientesNuevosMes }}</div>
                            <div class="kpi-label">Clientes nuevos</div>
                            <div class="kpi-trend text-muted"><i class="bi bi-people"></i> {{ $clientesTotal }} en total</div>
                        </div>
                        <div class="kpi-icon" style="background: #ec4899;"><i class="bi bi-person-plus"></i></div>
                    </div>
                </div>
            </div>
        @endif

        @if($show['inventario'])
            <div class="col-xl-3 col-md-6">
                <div class="kpi-card">
                    <div class="kpi-body">
                        <div>
                            <div class="kpi-value {{ $stockBajoCount > 0 ? 'text-danger' : '' }}">{{ $stockBajoCount }}</div>
                            <div class="kpi-label">Stock bajo</div>
                            <div class="kpi-trend text-muted"><i class="bi bi-box-seam"></i> productos a reponer</div>
                        </div>
                        <div class="kpi-icon" style="background: {{ $stockBajoCount > 0 ? '#ef4444' : '#f59e0b' }};"><i class="bi bi-boxes"></i></div>
                    </div>
                </div>
            </div>
        @endif
    </div>

    {{-- ===== Caja + ventas del mes ===== --}}
    @if($show['caja'] || $show['ventas'])
        <div class="row g-3 mb-4">
            @if($show['caja'])
                <div class="col-md-6">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body d-flex justify-content-between align-items-center">
                            <div>
                                <div class="text-muted small mb-1"><i class="bi bi-safe"></i> Caja</div>
                                @if(!empty($cajaAbierta))
                                    <div class="fw-bold fs-5">{{ $cajaAbierta->register?->name }}</div>
                                    <span class="badge bg-success">Abierta</span>
                                    <span class="text-muted small ms-1">Saldo esperado: <strong>{{ number_format($cajaAbierta->expectedBalance(), 2) }}</strong></span>
                                @else
                                    <div class="fw-bold fs-5 text-muted">Sin caja abierta</div>
                                    <span class="text-muted small">Abre una caja para registrar ventas en efectivo.</span>
                                @endif
                            </div>
                            <div>
                                @if(!empty($cajaAbierta))
                                    <a href="{{ route('cash-sessions.show', $cajaAbierta) }}" class="btn btn-outline-primary btn-sm">Ver caja</a>
                                @else
                                    <a href="{{ route('cash-sessions.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-unlock"></i> Abrir caja</a>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            @if($show['ventas'])
                <div class="col-md-6">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <div class="text-muted small mb-1"><i class="bi bi-graph-up"></i> Ventas del mes</div>
                            <div class="fw-bold fs-4">{{ number_format($ventasMesTotal, 2) }}</div>
                            <div class="text-muted small">Acumulado de {{ now()->translatedFormat('F') }}</div>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    @endif

    <div class="row g-3">
        {{-- ===== Citas de hoy ===== --}}
        @if($show['agenda'])
            <div class="col-lg-7">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body p-0">
                        <div class="d-flex justify-content-between align-items-center p-3">
                            <h6 class="fw-bold mb-0"><i class="bi bi-calendar-week"></i> Citas de hoy</h6>
                            <a href="{{ route('appointments.index') }}" class="btn btn-sm btn-outline-secondary">Ver todas</a>
                        </div>
                        <div class="table-responsive">
                            <table class="table align-middle mb-0">
                                <thead class="table-light">
                                    <tr><th class="ps-3">Hora</th><th>Cliente</th><th>Servicio</th><th>Especialista</th><th class="text-center pe-3">Estado</th></tr>
                                </thead>
                                <tbody>
                                    @forelse($citasHoyList as $cita)
                                        <tr>
                                            <td class="ps-3 fw-semibold">{{ $cita->starts_at->format('H:i') }}</td>
                                            <td>{{ $cita->client?->full_name ?? '—' }}</td>
                                            <td>{{ $cita->service?->name ?? '—' }}</td>
                                            <td>{{ $cita->specialist?->full_name ?? '—' }}</td>
                                            <td class="text-center pe-3"><span class="badge {{ $cita->statusBadge() }}">{{ $cita->statusLabel() }}</span></td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="5" class="text-center py-4 text-muted">No hay citas programadas para hoy.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        <div class="col-lg-5">
            {{-- ===== Stock bajo ===== --}}
            @if($show['inventario'])
                <div class="card border-0 shadow-sm mb-3">
                    <div class="card-body p-0">
                        <div class="d-flex justify-content-between align-items-center p-3">
                            <h6 class="fw-bold mb-0"><i class="bi bi-exclamation-triangle text-warning"></i> Stock bajo</h6>
                            <a href="{{ route('stock.index', ['low' => 1]) }}" class="btn btn-sm btn-outline-secondary">Ver</a>
                        </div>
                        <ul class="list-group list-group-flush">
                            @forelse($stockBajoList as $p)
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <span>{{ $p->name }}</span>
                                    <span class="badge bg-danger">{{ rtrim(rtrim(number_format($p->current_stock, 2), '0'), '.') }} / {{ rtrim(rtrim(number_format($p->min_stock, 2), '0'), '.') }}</span>
                                </li>
                            @empty
                                <li class="list-group-item text-muted text-center py-4">Todo el stock está en orden.</li>
                            @endforelse
                        </ul>
                    </div>
                </div>
            @endif

            {{-- ===== Cumpleaños ===== --}}
            @if($show['clientes'] && $cumpleanosHoy->count())
                <div class="card border-0 shadow-sm mb-3">
                    <div class="card-body">
                        <h6 class="fw-bold mb-2"><i class="bi bi-cake2 text-danger"></i> Cumpleaños de hoy</h6>
                        @foreach($cumpleanosHoy as $c)
                            <div class="d-flex justify-content-between align-items-center py-1">
                                <span>{{ $c->full_name }}</span>
                                <span class="text-muted small">{{ $c->phone ?: '—' }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- ===== Últimas ventas ===== --}}
            @if($show['ventas'])
                <div class="card border-0 shadow-sm">
                    <div class="card-body p-0">
                        <div class="d-flex justify-content-between align-items-center p-3">
                            <h6 class="fw-bold mb-0"><i class="bi bi-receipt"></i> Últimas ventas</h6>
                            <a href="{{ route('sales.index') }}" class="btn btn-sm btn-outline-secondary">Ver</a>
                        </div>
                        <ul class="list-group list-group-flush">
                            @forelse($ultimasVentas as $v)
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <span>
                                        <span class="fw-semibold">{{ $v->sale_number }}</span>
                                        <small class="text-muted d-block">{{ $v->clientDisplay() }}</small>
                                    </span>
                                    <span class="fw-semibold">{{ number_format($v->total, 2) }}</span>
                                </li>
                            @empty
                                <li class="list-group-item text-muted text-center py-4">Aún no hay ventas registradas.</li>
                            @endforelse
                        </ul>
                    </div>
                </div>
            @endif
        </div>
    </div>

    {{-- Si no hay ningún bloque visible --}}
    @if(!collect($show)->contains(true))
        <div class="card border-0 shadow-sm">
            <div class="card-body text-center py-5">
                <i class="bi bi-grid-1x2 text-muted" style="font-size: 3rem;"></i>
                <h5 class="mt-3 fw-semibold">Bienvenido</h5>
                <p class="text-muted mb-0">Tu plan o tus permisos no habilitan módulos con información para mostrar aquí.</p>
            </div>
        </div>
    @endif
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
