@extends('layouts.app')

@section('title', 'Horarios')

@section('page')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div>
            <h1 class="mb-1"><i class="bi bi-clock"></i> Horarios</h1>
            <p class="text-muted mb-0">Cuándo trabaja cada profesional. La agenda los usa para evitar citas fuera de turno.</p>
        </div>
        @if(empty($noCompany))
            <a href="{{ route('appointments.calendar') }}" class="btn btn-outline-secondary"><i class="bi bi-calendar-week"></i> Ver calendario</a>
        @endif
    </div>

    @if(!empty($noCompany))
        <div class="alert alert-info"><i class="bi bi-info-circle-fill"></i> Los horarios se configuran dentro de una empresa.</div>
    @elseif($people->isEmpty())
        <div class="card border-0 shadow-sm"><div class="card-body text-center text-muted py-5">
            <i class="bi bi-people" style="font-size: 2rem;"></i>
            <p class="mt-2 mb-0">No hay personal activo. <a href="{{ route('personal.create') }}">Registra a tu equipo</a> para cargar sus horarios.</p>
        </div></div>
    @else
        @php
            $todayIso = today()->dayOfWeekIso;
            $working = $people->filter(fn ($p) => !empty($today[$p->id]['blocks']));
            $withoutSchedule = $people->filter(fn ($p) => $p->schedules->isEmpty());
        @endphp

        <div class="row g-3 mb-3">
            <div class="col-md-4">
                <div class="card border-0 shadow-sm h-100"><div class="card-body">
                    <div class="text-muted small"><i class="bi bi-person-check text-success"></i> Trabajan hoy</div>
                    <div class="fs-4 fw-bold">{{ $working->count() }} <span class="fs-6 text-muted fw-normal">de {{ $people->count() }}</span></div>
                </div></div>
            </div>
            <div class="col-md-4">
                <div class="card border-0 shadow-sm h-100"><div class="card-body">
                    <div class="text-muted small"><i class="bi bi-calendar-x text-secondary"></i> Ausencias programadas</div>
                    <div class="fs-4 fw-bold">{{ $upcoming->where('type', 'off')->count() }}</div>
                </div></div>
            </div>
            <div class="col-md-4">
                <div class="card border-0 shadow-sm h-100"><div class="card-body">
                    <div class="text-muted small"><i class="bi bi-exclamation-circle text-warning"></i> Sin horario cargado</div>
                    <div class="fs-4 fw-bold">{{ $withoutSchedule->count() }}</div>
                    @if($withoutSchedule->isNotEmpty())<div class="small text-muted">La agenda no les aplica restricciones.</div>@endif
                </div></div>
            </div>
        </div>

        <div class="row g-3">
            <div class="col-xl-9">
                <div class="card border-0 shadow-sm">
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th class="ps-3">Profesional</th>
                                        @foreach(\App\Models\StaffSchedule::WEEKDAYS as $n => $name)
                                            <th class="text-center {{ $n === $todayIso ? 'text-primary' : '' }}">{{ mb_substr($name, 0, 3) }}</th>
                                        @endforeach
                                        <th class="pe-3"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($people as $p)
                                        @php $byDay = $p->schedules->groupBy('weekday'); $day = $today[$p->id]; @endphp
                                        <tr>
                                            <td class="ps-3">
                                                <div class="fw-semibold">{{ $p->full_name }}</div>
                                                <div class="small text-muted">
                                                    {{ $p->cargo?->name }}
                                                    @if($day['off'])<span class="badge bg-secondary ms-1">Hoy: {{ $day['off'] }}</span>
                                                    @elseif($day['special'])<span class="badge bg-warning text-dark ms-1">Hoy: horario especial</span>@endif
                                                </div>
                                            </td>
                                            @foreach(\App\Models\StaffSchedule::WEEKDAYS as $n => $name)
                                                <td class="text-center small {{ $n === $todayIso ? 'bg-primary-subtle' : '' }}">
                                                    @forelse($byDay->get($n, collect()) as $b)
                                                        <div class="text-nowrap">{{ $b->label() }}</div>
                                                    @empty
                                                        <span class="text-muted">—</span>
                                                    @endforelse
                                                </td>
                                            @endforeach
                                            <td class="pe-3 text-end">
                                                <a href="{{ route('schedules.edit', $p) }}" class="btn btn-sm {{ $p->schedules->isEmpty() ? 'btn-primary' : 'btn-outline-primary' }}">
                                                    @if($canEdit)<i class="bi bi-pencil"></i> {{ $p->schedules->isEmpty() ? 'Cargar' : 'Editar' }}@else<i class="bi bi-eye"></i>@endif
                                                </a>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-0 pt-3"><h6 class="fw-bold mb-0"><i class="bi bi-calendar-event"></i> Próximas excepciones</h6></div>
                    @if($upcoming->isEmpty())
                        <div class="card-body pt-0 small text-muted">No hay días libres ni horarios especiales programados.</div>
                    @else
                        <ul class="list-group list-group-flush small">
                            @foreach($upcoming as $e)
                                <li class="list-group-item">
                                    <a href="{{ route('schedules.edit', $e->personal_id) }}" class="fw-semibold text-decoration-none">{{ $e->specialist?->full_name }}</a>
                                    <div class="d-flex justify-content-between">
                                        <span>{{ $e->rangeLabel() }}</span>
                                        <span class="badge {{ $e->type === 'off' ? 'bg-secondary' : 'bg-warning text-dark' }}">{{ $e->type === 'off' ? ($e->reason ?: 'Libre') : $e->timeLabel() }}</span>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </div>
        </div>
    @endif
</div>
@endsection
