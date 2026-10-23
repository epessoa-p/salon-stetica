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
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                    <div class="d-flex align-items-center gap-2">
                        <label for="cal-person" class="small text-muted mb-0 text-nowrap"><i class="bi bi-person-badge"></i> Profesional</label>
                        <select id="cal-person" class="form-select form-select-sm" style="min-width: 220px;">
                            <option value="">Todo el equipo</option>
                            @foreach($specialists as $sp)<option value="{{ $sp->id }}">{{ $sp->full_name }}</option>@endforeach
                        </select>
                    </div>
                    <div class="small text-muted d-none" id="cal-schedule-legend">
                        <span style="width:12px;height:12px;border-radius:3px;background:#10b98140;display:inline-block;"></span> Horario
                        <span class="ms-2" style="width:12px;height:12px;border-radius:3px;background:#f59e0b40;display:inline-block;"></span> Horario especial
                        <span class="ms-2" style="width:12px;height:12px;border-radius:3px;background:#9ca3af55;display:inline-block;"></span> Libre
                    </div>
                </div>
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

        var person = document.getElementById('cal-person');
        // Recuerda el profesional elegido entre visitas.
        try { person.value = localStorage.getItem('calPerson') || ''; } catch (e) {}

        var calendar = new FullCalendar.Calendar(el, {
            initialView: 'dayGridMonth',
            locale: 'es',
            height: 'auto',
            headerToolbar: {
                left: 'prev,next today',
                center: 'title',
                right: 'dayGridMonth,timeGridWeek,timeGridDay'
            },
            // Citas (filtradas por profesional) y, si se elige uno, su horario de fondo.
            eventSources: [
                { url: '{{ route('appointments.events') }}', extraParams: function () { return { personal_id: person.value }; } },
                { url: '{{ route('appointments.schedule-events') }}', extraParams: function () { return { personal_id: person.value }; } }
            ],
            eventDisplay: 'block',
            dateClick: function (info) {
                window.location = '{{ route('appointments.create') }}?date=' + info.dateStr.slice(0, 10) + (person.value ? '&personal_id=' + person.value : '');
            },
            eventClick: function (info) {
                if (info.event.url) {
                    info.jsEvent.preventDefault();
                    window.location = info.event.url;
                }
            }
        });
        calendar.render();

        function onPerson() {
            document.getElementById('cal-schedule-legend').classList.toggle('d-none', !person.value);
            try { localStorage.setItem('calPerson', person.value); } catch (e) {}
            calendar.refetchEvents();
        }
        person.addEventListener('change', function () {
            onPerson();
            // Con un profesional elegido, la vista semanal muestra mejor su horario.
            if (person.value && calendar.view.type === 'dayGridMonth') calendar.changeView('timeGridWeek');
        });
        document.getElementById('cal-schedule-legend').classList.toggle('d-none', !person.value);
    });
</script>
@endpush
@endif
