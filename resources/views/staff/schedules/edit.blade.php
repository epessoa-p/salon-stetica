@extends('layouts.app')

@section('title', 'Horario de ' . $personal->full_name)

@section('page')
@php
    // Bloques iniciales por día: los guardados, o lo enviado si la validación falló.
    $initial = [];
    foreach (\App\Models\StaffSchedule::WEEKDAYS as $n => $name) {
        $saved = $weekly->get($n, collect());
        $initial[$n] = [
            'on'     => (bool) old("days.$n.on", $saved->isNotEmpty()),
            'blocks' => old("days.$n.blocks", $saved->map(fn ($b) => ['start' => substr($b->starts_time, 0, 5), 'end' => substr($b->ends_time, 0, 5)])->values()->all()),
        ];
    }
@endphp
<div class="container-fluid" style="max-width: 1100px;">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div>
            <h1 class="mb-1"><i class="bi bi-clock"></i> {{ $personal->full_name }}</h1>
            <p class="text-muted mb-0">{{ $personal->cargo?->name ?? 'Sin cargo' }} · horario semanal y excepciones.</p>
        </div>
        <a href="{{ route('schedules.index') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Horarios</a>
    </div>

    @if($errors->any())
        <div class="alert alert-danger"><ul class="mb-0 ps-3">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
    @endif

    <div class="row g-3">
        {{-- ================= Horario semanal ================= --}}
        <div class="col-lg-7">
            <form action="{{ route('schedules.update', $personal) }}" method="POST" class="card border-0 shadow-sm" id="weekly-form">
                @csrf @method('PUT')
                <div class="card-header bg-white border-0 pt-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <h6 class="fw-bold mb-0"><i class="bi bi-calendar-week text-primary"></i> Horario semanal</h6>
                    @if($canEdit)
                        <div class="d-flex gap-1 flex-wrap">
                            <button type="button" class="btn btn-sm btn-light border" data-preset="full">Lun–Vie 9–18</button>
                            <button type="button" class="btn btn-sm btn-light border" data-preset="split">Partido 9–13 / 14–19</button>
                            <button type="button" class="btn btn-sm btn-light border" data-preset="sat">+ Sáb 9–14</button>
                        </div>
                    @endif
                </div>
                <div class="card-body pt-2">
                    <fieldset @disabled(!$canEdit)>
                        <div id="week"></div>
                    </fieldset>
                </div>
                <div class="card-footer bg-white d-flex justify-content-between align-items-center">
                    <span class="small text-muted"><i class="bi bi-hourglass-split"></i> Total semanal: <strong id="week-total">0 h</strong></span>
                    @if($canEdit)
                        <button class="btn btn-primary"><i class="bi bi-check-lg"></i> Guardar horario</button>
                    @endif
                </div>
            </form>
            <p class="small text-muted mt-2 mb-0">
                <i class="bi bi-info-circle"></i> Sin ningún día marcado, la agenda no le aplica restricciones de horario.
                Con horario cargado, las citas fuera de él piden confirmación y las que se superponen se bloquean.
            </p>
        </div>

        {{-- ================= Excepciones ================= --}}
        <div class="col-lg-5">
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-white border-0 pt-3"><h6 class="fw-bold mb-0"><i class="bi bi-calendar-x text-secondary"></i> Excepciones</h6></div>
                @if($exceptions->isEmpty())
                    <div class="card-body pt-0 small text-muted">Sin días libres, vacaciones ni horarios especiales.</div>
                @else
                    <ul class="list-group list-group-flush small">
                        @foreach($exceptions as $e)
                            <li class="list-group-item d-flex justify-content-between align-items-center {{ $e->date_to->lt(today()) ? 'text-muted' : '' }}">
                                <div>
                                    <span class="badge {{ $e->type === 'off' ? 'bg-secondary' : 'bg-warning text-dark' }}">{{ \App\Models\StaffScheduleException::TYPES[$e->type] }}</span>
                                    <span class="fw-semibold ms-1">{{ $e->rangeLabel() }}</span>
                                    @if($e->timeLabel())<span class="ms-1">{{ $e->timeLabel() }}</span>@endif
                                    @if($e->reason)<div class="text-muted">{{ $e->reason }}</div>@endif
                                </div>
                                @if($canEdit)
                                    <form action="{{ route('schedules.exceptions.destroy', [$personal, $e]) }}" method="POST" onsubmit="return confirm('¿Eliminar esta excepción?')">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                    </form>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>

            @if($canEdit)
                <form action="{{ route('schedules.exceptions.store', $personal) }}" method="POST" class="card border-0 shadow-sm" id="exception-form">
                    @csrf
                    <div class="card-header bg-white border-0 pt-3"><h6 class="fw-bold mb-0"><i class="bi bi-plus-circle"></i> Agregar excepción</h6></div>
                    <div class="card-body pt-2">
                        <div class="btn-group w-100 mb-3" role="group">
                            <input type="radio" class="btn-check" name="type" id="t-off" value="off" @checked(old('type', 'off') === 'off')>
                            <label class="btn btn-outline-secondary btn-sm" for="t-off"><i class="bi bi-calendar-x"></i> No trabaja</label>
                            <input type="radio" class="btn-check" name="type" id="t-custom" value="custom" @checked(old('type') === 'custom')>
                            <label class="btn btn-outline-warning btn-sm" for="t-custom"><i class="bi bi-clock-history"></i> Horario especial</label>
                        </div>

                        <div class="row g-2">
                            <div class="col-6">
                                <label class="form-label small mb-1" id="l-from">Desde</label>
                                <input type="date" name="date_from" class="form-control form-control-sm" required value="{{ old('date_from', today()->toDateString()) }}">
                            </div>
                            <div class="col-6 only-off">
                                <label class="form-label small mb-1">Hasta</label>
                                <input type="date" name="date_to" class="form-control form-control-sm" value="{{ old('date_to') }}">
                            </div>
                            <div class="col-3 only-custom">
                                <label class="form-label small mb-1">Entrada</label>
                                <input type="time" name="starts_time" class="form-control form-control-sm" value="{{ old('starts_time', '09:00') }}">
                            </div>
                            <div class="col-3 only-custom">
                                <label class="form-label small mb-1">Salida</label>
                                <input type="time" name="ends_time" class="form-control form-control-sm" value="{{ old('ends_time', '13:00') }}">
                            </div>
                            <div class="col-12">
                                <label class="form-label small mb-1">Motivo</label>
                                <input type="text" name="reason" class="form-control form-control-sm" maxlength="150" list="reasons" value="{{ old('reason') }}" placeholder="Vacaciones, día libre, permiso…">
                                <datalist id="reasons">
                                    @foreach(\App\Models\StaffScheduleException::REASONS as $r)<option value="{{ $r }}">@endforeach
                                </datalist>
                            </div>
                        </div>
                        <div class="form-text only-off">Para un solo día deja "Hasta" vacío.</div>
                        <div class="form-text only-custom">Reemplaza su horario semanal solo ese día.</div>
                        <button class="btn btn-outline-primary btn-sm w-100 mt-3">Agregar</button>
                    </div>
                </form>
            @endif
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    const DAYS = @json(\App\Models\StaffSchedule::WEEKDAYS);
    const state = @json($initial);
    const box = document.getElementById('week');
    const minutes = (t) => { const [h, m] = (t || '0:0').split(':'); return (+h) * 60 + (+m); };

    function render() {
        box.innerHTML = Object.keys(DAYS).map((n) => {
            const d = state[n];
            const blocks = d.on ? d.blocks.map((b, i) => `
                <div class="d-flex align-items-center gap-1">
                    <input type="time" class="form-control form-control-sm" style="width: 105px;" name="days[${n}][blocks][${i}][start]" value="${b.start || ''}" data-n="${n}" data-i="${i}" data-k="start">
                    <span class="text-muted">–</span>
                    <input type="time" class="form-control form-control-sm" style="width: 105px;" name="days[${n}][blocks][${i}][end]" value="${b.end || ''}" data-n="${n}" data-i="${i}" data-k="end">
                    ${d.blocks.length > 1 ? `<button type="button" class="btn btn-sm btn-link text-danger p-0" data-del="${n}:${i}" title="Quitar bloque"><i class="bi bi-x-lg"></i></button>` : ''}
                </div>`).join('') : '<span class="text-muted small">No trabaja</span>';

            return `<div class="d-flex align-items-start gap-3 py-2 border-bottom">
                <div class="form-check form-switch pt-1" style="width: 130px;">
                    <input class="form-check-input" type="checkbox" id="d${n}" name="days[${n}][on]" value="1" data-on="${n}" ${d.on ? 'checked' : ''}>
                    <label class="form-check-label fw-semibold" for="d${n}">${DAYS[n]}</label>
                </div>
                <div class="d-flex flex-column gap-1 flex-grow-1">${blocks}</div>
                <div class="d-flex gap-1">
                    ${d.on && d.blocks.length < 3 ? `<button type="button" class="btn btn-sm btn-light border" data-add="${n}" title="Agregar bloque (turno partido)"><i class="bi bi-plus"></i></button>` : ''}
                    ${d.on && n !== '1' ? `<button type="button" class="btn btn-sm btn-light border" data-copy="${n}" title="Copiar el horario del lunes"><i class="bi bi-files"></i></button>` : ''}
                </div>
            </div>`;
        }).join('');
        total();
    }

    function total() {
        let mins = 0;
        Object.values(state).forEach((d) => { if (d.on) d.blocks.forEach((b) => { if (b.start && b.end) mins += Math.max(0, minutes(b.end) - minutes(b.start)); }); });
        const h = Math.floor(mins / 60), m = mins % 60;
        document.getElementById('week-total').textContent = h + ' h' + (m ? ' ' + m + ' min' : '');
    }

    box.addEventListener('change', (e) => {
        const on = e.target.dataset.on;
        if (on) {
            state[on].on = e.target.checked;
            if (e.target.checked && !state[on].blocks.length) state[on].blocks = [{ start: '09:00', end: '18:00' }];
            render();
        }
    });
    box.addEventListener('input', (e) => {
        const { n, i, k } = e.target.dataset;
        if (n !== undefined && k) { state[n].blocks[i][k] = e.target.value; total(); }
    });
    box.addEventListener('click', (e) => {
        const add = e.target.closest('[data-add]'), del = e.target.closest('[data-del]'), copy = e.target.closest('[data-copy]');
        if (add) { state[add.dataset.add].blocks.push({ start: '14:00', end: '19:00' }); render(); }
        if (del) { const [n, i] = del.dataset.del.split(':'); state[n].blocks.splice(+i, 1); render(); }
        if (copy) { state[copy.dataset.copy] = { on: state[1].on, blocks: state[1].blocks.map((b) => ({ ...b })) }; render(); }
    });

    const presets = {
        full:  () => [1, 2, 3, 4, 5].forEach((n) => { state[n] = { on: true, blocks: [{ start: '09:00', end: '18:00' }] }; }),
        split: () => [1, 2, 3, 4, 5].forEach((n) => { state[n] = { on: true, blocks: [{ start: '09:00', end: '13:00' }, { start: '14:00', end: '19:00' }] }; }),
        sat:   () => { state[6] = { on: true, blocks: [{ start: '09:00', end: '14:00' }] }; },
    };
    document.querySelectorAll('[data-preset]').forEach((b) => b.addEventListener('click', () => { presets[b.dataset.preset](); render(); }));

    // Formulario de excepciones: muestra los campos según el tipo.
    const form = document.getElementById('exception-form');
    function syncType() {
        if (!form) return;
        const custom = form.querySelector('[name="type"]:checked').value === 'custom';
        form.querySelectorAll('.only-off').forEach((el) => el.classList.toggle('d-none', custom));
        form.querySelectorAll('.only-custom').forEach((el) => el.classList.toggle('d-none', !custom));
        document.getElementById('l-from').textContent = custom ? 'Fecha' : 'Desde';
    }
    form?.addEventListener('change', syncType);
    syncType();
    render();
})();
</script>
@endpush
