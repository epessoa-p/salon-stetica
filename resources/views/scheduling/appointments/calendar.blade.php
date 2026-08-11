@extends('layouts.app')

@section('title', 'Calendario')

@section('page')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h1 class="mb-1"><i class="bi bi-calendar-week"></i> Calendario</h1>
            <p class="text-muted mb-0">Agenda de citas. Haz clic en un día para crear una cita.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('appointments.index') }}" class="btn btn-outline-primary"><i class="bi bi-list-ul"></i> Lista</a>
            <a href="{{ route('appointments.create') }}" class="btn btn-primary"><i class="bi bi-plus-circle"></i> Nueva Cita</a>
        </div>
    </div>

    @if($noCompany)
        <div class="alert alert-info d-flex align-items-center gap-2">
            <i class="bi bi-info-circle-fill"></i> La agenda se gestiona dentro de una empresa. Selecciona o ingresa a una empresa para ver su calendario.
        </div>
    @else
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                {{-- Leyenda de estados --}}
                <div class="d-flex flex-wrap gap-3 mb-3 small">
                    @foreach(\App\Models\Appointment::STATUSES as $meta)
                        <span class="d-inline-flex align-items-center gap-1">
                            <span style="width:12px;height:12px;border-radius:3px;background:{{ $meta[1] }};display:inline-block;"></span>
                            {{ $meta[0] }}
                        </span>
                    @endforeach
                </div>
                <div id="calendar"></div>
            </div>
        </div>
    @endif
</div>
@endsection

@if(!$noCompany)
@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/locales-all.global.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        var el = document.getElementById('calendar');
        if (!el || typeof FullCalendar === 'undefined') return;

        var calendar = new FullCalendar.Calendar(el, {
            initialView: 'dayGridMonth',
            locale: 'es',
            height: 'auto',
            headerToolbar: {
                left: 'prev,next today',
                center: 'title',
                right: 'dayGridMonth,timeGridWeek,timeGridDay'
            },
            events: '{{ route('appointments.events') }}',
            eventDisplay: 'block',
            dateClick: function (info) {
                window.location = '{{ route('appointments.create') }}?date=' + info.dateStr;
            },
            eventClick: function (info) {
                if (info.event.url) {
                    info.jsEvent.preventDefault();
                    window.location = info.event.url;
                }
            }
        });
        calendar.render();
    });
</script>
@endpush
@endif
