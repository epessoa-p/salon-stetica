@extends('layouts.app')

@section('title', 'Asistencia')

@section('page')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div>
            <h1 class="mb-1"><i class="bi bi-calendar2-check"></i> Asistencia</h1>
            <p class="text-muted mb-0">Entradas y salidas del equipo contra su horario.@if(empty($noCompany)) Tolerancia de tardanza: {{ $tolerance }} min.@endif</p>
        </div>
        @if(empty($noCompany))
            <a href="{{ route('schedules.index') }}" class="btn btn-outline-secondary"><i class="bi bi-clock"></i> Horarios</a>
        @endif
    </div>

    @if(!empty($noCompany))
        <div class="alert alert-info"><i class="bi bi-info-circle-fill"></i> La asistencia se gestiona dentro de una empresa.</div>
    @else
        @if($errors->any())
            <div class="alert alert-danger"><ul class="mb-0 ps-3">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
        @endif

        {{-- ================= Hoy ================= --}}
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white border-0 pt-3 d-flex justify-content-between align-items-center">
                <h6 class="fw-bold mb-0"><i class="bi bi-sun"></i> Hoy · {{ today()->translatedFormat('l d/m') }}</h6>
                @php $counts = collect($board)->countBy('state'); @endphp
                <div class="small d-flex gap-2 flex-wrap">
                    <span class="badge bg-primary">{{ $counts['working'] ?? 0 }} en turno</span>
                    @if(($counts['late'] ?? 0))<span class="badge bg-warning text-dark">{{ $counts['late'] }} tarde</span>@endif
                    @if(($counts['missing'] ?? 0))<span class="badge bg-danger">{{ $counts['missing'] }} sin marcar</span>@endif
                </div>
            </div>
            <div class="card-body p-0">
                @if(empty($board))
                    <p class="text-muted text-center py-4 mb-0">No hay personal activo.</p>
                @else
                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-3">Profesional</th>
                                    <th>Horario de hoy</th>
                                    <th>Marcas</th>
                                    <th class="text-end">Horas</th>
                                    <th class="text-center">Estado</th>
                                    @if($canMark)<th class="pe-3 text-end"></th>@endif
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($board as $row)
                                    @php $p = $row['personal']; @endphp
                                    <tr>
                                        <td class="ps-3">
                                            <a href="{{ route('attendance.index', ['personal_id' => $p->id]) }}" class="fw-semibold text-decoration-none">{{ $p->full_name }}</a>
                                            @unless($p->user_id)<div class="small text-muted"><i class="bi bi-person-dash"></i> sin usuario: marca recepción</div>@endunless
                                        </td>
                                        <td class="small text-muted">{{ $row['blocks'] ? collect($row['blocks'])->map(fn ($b) => $b['start'] . '–' . $b['end'])->implode(' · ') : '—' }}</td>
                                        <td class="small">
                                            @forelse($row['records'] as $r)
                                                <span class="text-nowrap me-2">{{ $r->check_in->format('H:i') }} → {{ $r->check_out?->format('H:i') ?? '…' }}</span>
                                                @if(!$r->work_date->isToday())<span class="badge bg-danger-subtle text-danger-emphasis" title="Entrada de otro día sin salida">desde {{ $r->work_date->format('d/m') }}</span>@endif
                                            @empty
                                                <span class="text-muted">—</span>
                                            @endforelse
                                        </td>
                                        <td class="text-end small">{{ $row['worked'] ? \App\Models\Attendance::duration($row['worked']) : '—' }}</td>
                                        <td class="text-center"><span class="badge {{ $row['badge'] }}">{{ $row['label'] }}</span></td>
                                        @if($canMark)
                                            <td class="pe-3 text-end">
                                                <form action="{{ route('attendance.mark') }}" method="POST" class="d-inline">
                                                    @csrf
                                                    <input type="hidden" name="personal_id" value="{{ $p->id }}">
                                                    @if($row['open'])
                                                        <button name="action" value="out" class="btn btn-sm btn-outline-danger text-nowrap"><i class="bi bi-box-arrow-right"></i> Salida</button>
                                                    @else
                                                        <button name="action" value="in" class="btn btn-sm btn-outline-success text-nowrap"><i class="bi bi-box-arrow-in-right"></i> Entrada</button>
                                                    @endif
                                                </form>
                                            </td>
                                        @endif
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>

        {{-- ================= Reporte ================= --}}
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-0 pt-3"><h6 class="fw-bold mb-0"><i class="bi bi-bar-chart"></i> Reporte por persona</h6></div>
            <div class="card-body pt-2">
                <form method="GET" class="row g-2 align-items-end mb-3">
                    <div class="col-md-4">
                        <label class="form-label small mb-1">Profesional</label>
                        <select name="personal_id" class="form-select form-select-sm" required>
                            <option value="">Seleccionar...</option>
                            @foreach($people as $p)<option value="{{ $p->id }}" @selected($selected && $selected->id === $p->id)>{{ $p->full_name }}</option>@endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small mb-1">Desde</label>
                        <input type="date" name="from" class="form-control form-control-sm" value="{{ $from->toDateString() }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small mb-1">Hasta</label>
                        <input type="date" name="to" class="form-control form-control-sm" value="{{ $to->toDateString() }}">
                    </div>
                    <div class="col-md-2 d-grid">
                        <button class="btn btn-sm btn-primary"><i class="bi bi-funnel"></i> Ver</button>
                    </div>
                </form>

                @if($report)
                    @include('staff.attendance._report', ['report' => $report, 'canEdit' => $canEdit])
                @else
                    <p class="text-muted small mb-0">Elige una persona para ver sus días trabajados, horas, tardanzas y ausencias. Por defecto, el mes en curso.</p>
                @endif
            </div>
        </div>
    @endif
</div>
@endsection
