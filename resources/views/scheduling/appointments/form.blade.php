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

    @if($errors->any())
        <div class="alert alert-danger"><ul class="mb-0 ps-3">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
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
                        <select name="personal_id" class="form-select">
                            <option value="">Sin asignar</option>
                            @foreach($specialists as $sp)
                                <option value="{{ $sp->id }}" {{ (string) old('personal_id', $appointment?->personal_id) === (string) $sp->id ? 'selected' : '' }}>{{ $sp->full_name }}</option>
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
                        <input type="datetime-local" name="starts_at" class="form-control no-uppercase" value="{{ $startValue }}" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Duración (min)</label>
                        <input type="number" min="0" name="duration_minutes" id="duration_minutes" class="form-control no-uppercase" value="{{ $durationValue }}" placeholder="30">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Precio</label>
                        <input type="number" step="0.01" min="0" name="price" id="price" class="form-control no-uppercase" value="{{ old('price', $appointment?->price) }}">
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
        });
    })();
</script>
@endpush
