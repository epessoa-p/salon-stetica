@php
    $startValue = old('starts_at');
    if (!$startValue) {
        if ($appointment) {
            $startValue = $appointment->starts_at->format('Y-m-d\TH:i');
        } elseif ($prefillDate) {
            $startValue = $prefillDate . 'T09:00';
        }
    }
    $durationValue = old('duration_minutes', $appointment && $appointment->starts_at && $appointment->ends_at
        ? $appointment->starts_at->diffInMinutes($appointment->ends_at)
        : null);
@endphp

<div class="container-fluid" style="max-width: 820px;">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h1 class="mb-1">{{ $appointment ? 'Editar cita' : 'Nueva cita' }}</h1>
            <p class="text-muted mb-0">Programa una cita para un cliente.</p>
        </div>
        <a href="{{ route('appointments.index') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Volver</a>
    </div>

    @php $generalErrors = collect($errors->getMessages())->except('outside_schedule')->flatten(); @endphp
    @if($generalErrors->isNotEmpty())
        <div class="alert alert-danger"><ul class="mb-0 ps-3">@foreach($generalErrors as $e)<li>{{ $e }}</li>@endforeach</ul></div>
    @endif

    <form action="{{ $action }}" method="POST">
        @csrf
        @if($method !== 'POST') @method($method) @endif

        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body p-4">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Cliente <span class="text-danger">*</span></label>
                        <select name="client_id" class="form-select" required>
                            <option value="">Seleccionar cliente</option>
                            @foreach($clients as $client)
                                <option value="{{ $client->id }}" {{ (string) old('client_id', $appointment?->client_id) === (string) $client->id ? 'selected' : '' }}>{{ $client->full_name }}</option>
                            @endforeach
                        </select>
                        @if($clients->isEmpty())<small class="text-muted">No hay clientes. <a href="{{ route('clients.create') }}">Crea uno</a>.</small>@endif
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Servicio</label>
                        <select name="service_id" id="service_id" class="form-select">
                            <option value="" data-price="" data-duration="">Sin servicio</option>
                            @foreach($services as $service)
                                <option value="{{ $service->id }}"
                                        data-price="{{ $service->price }}"
                                        data-duration="{{ $service->duration_minutes }}"
                                        {{ (string) old('service_id', $appointment?->service_id) === (string) $service->id ? 'selected' : '' }}>
                                    {{ $service->name }} ({{ number_format($service->price, 2) }})
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Especialista</label>
                        <select name="personal_id" id="personal_id" class="form-select">
                            <option value="">Sin asignar</option>
                            @foreach($specialists as $sp)
                                <option value="{{ $sp->id }}" {{ (string) old('personal_id', $appointment?->personal_id ?? request('personal_id')) === (string) $sp->id ? 'selected' : '' }}>{{ $sp->full_name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Sucursal</label>
                        <select name="branch_id" class="form-select">
                            <option value="">Sin sucursal</option>
                            @foreach($branches as $branch)
                                <option value="{{ $branch->id }}" {{ (string) old('branch_id', $appointment?->branch_id) === (string) $branch->id ? 'selected' : '' }}>{{ $branch->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-5">
                        <label class="form-label">Fecha y hora <span class="text-danger">*</span></label>
                        <input type="datetime-local" name="starts_at" id="starts_at" class="form-control no-uppercase" value="{{ $startValue }}" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Duración (min)</label>
                        <input type="number" min="0" name="duration_minutes" id="duration_minutes" class="form-control no-uppercase" value="{{ $durationValue }}" placeholder="30">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Precio</label>
                        <input type="number" step="0.01" min="0" name="price" id="price" class="form-control no-uppercase" value="{{ old('price', $appointment?->price) }}">
                    </div>

                    <div class="col-12">
                        <div id="availability" class="d-none small rounded-3 border p-2"></div>
                        @error('outside_schedule')
                            <div class="alert alert-warning mt-2 mb-0 py-2">
                                <div><i class="bi bi-exclamation-triangle-fill"></i> {{ $message }}</div>
                                <div class="form-check mt-1">
                                    <input class="form-check-input" type="checkbox" name="force_outside" value="1" id="force_outside">
                                    <label class="form-check-label fw-semibold" for="force_outside">Agendar de todos modos</label>
                                </div>
                            </div>
                        @enderror
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Estado</label>
                        <select name="status" class="form-select">
                            @foreach($statuses as $key => $meta)
                                <option value="{{ $key }}" {{ old('status', $appointment?->status ?? 'pendiente') === $key ? 'selected' : '' }}>{{ $meta[0] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Notas</label>
                        <textarea name="notes" rows="2" class="form-control">{{ old('notes', $appointment?->notes) }}</textarea>
                    </div>
                </div>
            </div>
        </div>

        <div class="d-flex gap-2">
            <button class="btn btn-primary px-4" type="submit"><i class="bi bi-save"></i> {{ $appointment ? 'Guardar cambios' : 'Crear cita' }}</button>
            <a href="{{ route('appointments.index') }}" class="btn btn-light border">Cancelar</a>
        </div>
    </form>
</div>

@push('scripts')
<script>
    (function () {
        var service = document.getElementById('service_id');
        var price = document.getElementById('price');
        var duration = document.getElementById('duration_minutes');
        if (!service) return;

        service.addEventListener('change', function () {
            var opt = service.options[service.selectedIndex];
            var p = opt.getAttribute('data-price');
            var d = opt.getAttribute('data-duration');
            // Solo autocompleta si el campo está vacío, para no pisar valores manuales.
            if (p && price && !price.value) price.value = p;
            if (d && duration && !duration.value) duration.value = d;
            refresh();
        });

        // ---- Disponibilidad del profesional en la fecha elegida ----
        var person = document.getElementById('personal_id');
        var startsAt = document.getElementById('starts_at');
        var panel = document.getElementById('availability');
        var url = @json(route('appointments.availability'));
        var ignore = @json($appointment?->id);
        var days = ['domingo', 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado'];

        function esc(s) { return String(s).replace(/[&<>"]/g, function (c) { return {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;'}[c]; }); }
        function minutes(hhmm) { var p = hhmm.split(':'); return +p[0] * 60 + +p[1]; }

        function refresh() {
            if (!person.value || !startsAt.value) { panel.classList.add('d-none'); return; }
            var date = startsAt.value.slice(0, 10);
            fetch(url + '?personal_id=' + person.value + '&date=' + date + (ignore ? '&ignore=' + ignore : ''), { headers: { Accept: 'application/json' } })
                .then(function (r) { return r.json(); })
                .then(function (d) { if (d) render(d, date); })
                .catch(function () { panel.classList.add('d-none'); });
        }

        function render(d, date) {
            var dt = new Date(date + 'T00:00');
            var head = '<strong>' + esc(d.name) + '</strong> · ' + days[dt.getDay()] + ' ' + date.split('-').reverse().join('/') + ': ';
            // Misma regla que el servidor: duración indicada, si no la del servicio, si no 30 min.
            var serviceDuration = parseInt(service.options[service.selectedIndex].getAttribute('data-duration'), 10);
            var from = minutes(startsAt.value.slice(11, 16));
            var to = from + (parseInt(duration.value, 10) || serviceDuration || 30);
            var state = 'info', schedule;

            if (d.off) {
                schedule = '<span class="text-danger">no trabaja (' + esc(d.off) + ')</span>';
                state = 'warning';
            } else if (!d.configured && !d.special) {
                schedule = '<span class="text-muted">sin horario cargado (sin restricción)</span>';
            } else if (!d.blocks.length) {
                schedule = '<span class="text-danger">no trabaja ese día</span>';
                state = 'warning';
            } else {
                schedule = d.blocks.map(function (b) { return b.start + '–' + b.end; }).join(' y ') + (d.special ? ' <span class="badge bg-warning text-dark">horario especial</span>' : '');
                var fits = d.blocks.some(function (b) { return from >= minutes(b.start) && to <= minutes(b.end); });
                if (!fits) { schedule += ' · <span class="text-danger">la hora elegida queda fuera</span>'; state = 'warning'; }
            }

            var clash = false;
            var busy = d.busy.length
                ? '<div class="mt-1 text-muted">Ocupado: ' + d.busy.map(function (b) {
                    var hit = from < minutes(b.end) && to > minutes(b.start);
                    if (hit) clash = true;
                    return '<span class="badge ' + (hit ? 'bg-danger' : 'bg-light text-dark border') + '">' + b.start + '–' + b.end + '</span> ' + esc(b.label);
                }).join(' · ') + '</div>'
                : '<div class="mt-1 text-muted">Sin citas ese día.</div>';
            if (clash) state = 'danger';

            panel.className = 'small rounded-3 border p-2 border-' + state + ' bg-' + state + '-subtle';
            panel.innerHTML = '<i class="bi bi-clock"></i> ' + head + schedule + busy
                + (clash ? '<div class="mt-1 text-danger fw-semibold"><i class="bi bi-x-octagon"></i> Choca con otra cita: no se podrá guardar.</div>' : '');
        }

        [person, startsAt, duration].forEach(function (el) { el && el.addEventListener('change', refresh); });
        refresh();
    })();
</script>
@endpush
