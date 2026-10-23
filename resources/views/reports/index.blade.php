@extends('layouts.app')

@section('title', 'Reportes')

@section('page')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h1 class="mb-1"><i class="bi bi-bar-chart"></i> Reportes</h1>
            <p class="text-muted mb-0">Analiza el desempeno de tu salon por periodo.</p>
        </div>
    </div>

    @if($noCompany)
        <div class="alert alert-info d-flex align-items-center gap-2"><i class="bi bi-info-circle-fill"></i> Los reportes se consultan dentro de una empresa.</div>
    @elseif(empty($available))
        <div class="alert alert-warning">Tu plan o tus permisos no habilitan ningun reporte.</div>
    @else
        {{-- Pestanas de tipo de reporte --}}
        <ul class="nav nav-tabs mb-3">
            @foreach($available as $key => $label)
                <li class="nav-item">
                    <a class="nav-link {{ $type === $key ? 'active' : '' }}"
                       href="{{ route('reports.index', ['type' => $key, 'from' => $from, 'to' => $to]) }}">{{ $label }}</a>
                </li>
            @endforeach
        </ul>

        {{-- Filtro de fechas --}}
        <form method="GET" class="card border-0 shadow-sm mb-3">
            <input type="hidden" name="type" value="{{ $type }}">
            <div class="card-body py-3">
                <div class="row g-2 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label small mb-1">Desde</label>
                        <input type="date" name="from" value="{{ $from }}" class="form-control no-uppercase">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small mb-1">Hasta</label>
                        <input type="date" name="to" value="{{ $to }}" class="form-control no-uppercase">
                    </div>
                    <div class="col-md-6 d-flex gap-2">
                        <button class="btn btn-outline-primary" type="submit"><i class="bi bi-funnel"></i> Aplicar</button>
                        <a href="{{ route('reports.index', ['type' => $type]) }}" class="btn btn-light border">Mes actual</a>
                        <button type="button" class="btn btn-light border ms-auto" onclick="window.print()"><i class="bi bi-printer"></i> Imprimir</button>
                    </div>
                </div>
            </div>
        </form>

        {{-- KPIs --}}
        <div class="row g-3 mb-4">
            @foreach($report['kpis'] ?? [] as $kpi)
                <div class="col-xl col-md-6">
                    <div class="kpi-card"><div class="kpi-body">
                        <div>
                            <div class="kpi-value">{{ $kpi['value'] }}</div>
                            <div class="kpi-label">{{ $kpi['label'] }}</div>
                        </div>
                        <div class="kpi-icon" style="background: {{ $kpi['color'] }};"><i class="bi {{ $kpi['icon'] }}"></i></div>
                    </div></div>
                </div>
            @endforeach
        </div>

        {{-- Tablas --}}
        <div class="row g-3">
            @foreach($report['tables'] ?? [] as $table)
                <div class="col-lg-6">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body p-0">
                            <div class="p-3"><h6 class="fw-bold mb-0">{{ $table['title'] }}</h6></div>
                            <div class="table-responsive">
                                <table class="table table-sm align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            @foreach($table['head'] as $i => $h)
                                                <th class="{{ $i === 0 ? 'ps-3' : ($i === count($table['head']) - 1 ? 'text-end pe-3' : 'text-end') }}">{{ $h }}</th>
                                            @endforeach
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($table['rows'] as $row)
                                            <tr>
                                                @foreach($row as $i => $cell)
                                                    <td class="{{ $i === 0 ? 'ps-3' : ($i === count($row) - 1 ? 'text-end pe-3' : 'text-end') }}">{{ $cell }}</td>
                                                @endforeach
                                            </tr>
                                        @empty
                                            <tr><td colspan="{{ count($table['head']) }}" class="text-center py-4 text-muted">Sin datos en el periodo.</td></tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>

@push('styles')
<style>
    .kpi-card { background: #fff; border-radius: 10px; padding: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); border: 0; height: 100%; }
    .kpi-body { display: flex; justify-content: space-between; align-items: flex-start; }
    .kpi-value { font-size: 1.8rem; font-weight: 700; line-height: 1; margin-bottom: 4px; }
    .kpi-label { font-size: 0.82rem; color: #777; }
    .kpi-icon { width: 46px; height: 46px; border-radius: 12px; display: grid; place-items: center; color: #fff; font-size: 1.2rem; flex-shrink: 0; }
    @media print {
        .app-sidebar, .app-topbar, form, .nav-tabs, .btn { display: none !important; }
        .app-main { padding: 0 !important; }
    }
</style>
@endpush
@endsection
