@extends('layouts.app')

@section('title', 'Mi asistencia')

@section('page')
<div class="container-fluid" style="max-width: 900px;">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div>
            <h1 class="mb-1"><i class="bi bi-person-check"></i> Mi asistencia</h1>
            <p class="text-muted mb-0">Marca tu entrada y salida, y revisa tu mes.</p>
        </div>
    </div>

    @if(!$personal)
        <div class="alert alert-info"><i class="bi bi-info-circle-fill"></i> Tu usuario no está vinculado a una ficha de personal en esta empresa, así que no registra asistencia.</div>
    @else
        @if($errors->any())
            <div class="alert alert-danger">{{ $errors->first() }}</div>
        @endif

        @php $today = $report['days'][today()->toDateString()] ?? null; @endphp
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body d-flex justify-content-between align-items-center flex-wrap gap-3">
                <div>
                    <div class="text-muted small">{{ today()->translatedFormat('l d \d\e F') }}</div>
                    <div class="fs-5 fw-bold">
                        @if($open)
                            En turno desde las {{ $open->check_in->format('H:i') }}
                        @elseif($today && $today['records']->isNotEmpty())
                            Jornada registrada · {{ \App\Models\Attendance::duration($today['worked']) }}
                        @else
                            Aún no marcaste tu entrada
                        @endif
                    </div>
                    <div class="small text-muted">
                        Tu horario de hoy:
                        {{ $today && $today['blocks'] ? collect($today['blocks'])->map(fn ($b) => $b['start'] . '–' . $b['end'])->implode(' y ') : ($today['label'] ?? '—') }}
                    </div>
                </div>
                <form action="{{ route('attendance.self') }}" method="POST">
                    @csrf
                    @if($open)
                        <button name="action" value="out" class="btn btn-lg btn-danger"><i class="bi bi-box-arrow-right"></i> Marcar salida</button>
                    @else
                        <button name="action" value="in" class="btn btn-lg btn-success"><i class="bi bi-box-arrow-in-right"></i> Marcar entrada</button>
                    @endif
                </form>
            </div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-0 pt-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                <h6 class="fw-bold mb-0">{{ $from->format('d/m') }} – {{ $to->format('d/m/Y') }}</h6>
                <form method="GET" class="d-flex gap-2">
                    <input type="date" name="from" class="form-control form-control-sm" value="{{ $from->toDateString() }}">
                    <input type="date" name="to" class="form-control form-control-sm" value="{{ $to->toDateString() }}">
                    <button class="btn btn-sm btn-outline-secondary"><i class="bi bi-funnel"></i></button>
                </form>
            </div>
            <div class="card-body pt-2">
                @include('staff.attendance._report', ['report' => $report, 'canEdit' => false])
            </div>
        </div>
    @endif
</div>
@endsection
