<div class="container-fluid" style="max-width: 900px;">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h1 class="mb-1">{{ $client ? 'Editar cliente' : 'Nuevo cliente' }}</h1>
            <p class="text-muted mb-0">Ficha del cliente del salón.</p>
        </div>
        <a href="{{ route('clients.index') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Volver</a>
    </div>

    @if($errors->any())
        <div class="alert alert-danger"><ul class="mb-0 ps-3">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
    @endif

    <form action="{{ $action }}" method="POST">
        @csrf
        @if($method !== 'POST') @method($method) @endif

        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body p-4">
                <h6 class="fw-bold mb-3"><i class="bi bi-person-lines-fill"></i> Datos del cliente</h6>
                <div class="row g-3">
                    @if(!$client && $companies->count() > 1)
                        <div class="col-md-4">
                            <label class="form-label">Empresa <span class="text-danger">*</span></label>
                            <select name="company_id" class="form-select" required>
                                <option value="">Seleccionar empresa</option>
                                @foreach($companies as $company)
                                    <option value="{{ $company->id }}" {{ (string) old('company_id') === (string) $company->id ? 'selected' : '' }}>{{ $company->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif

                    <div class="{{ (!$client && $companies->count() > 1) ? 'col-md-8' : 'col-md-6' }}">
                        <label class="form-label">Nombre completo <span class="text-danger">*</span></label>
                        <input type="text" name="full_name" class="form-control" value="{{ old('full_name', $client?->full_name) }}" required>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label">Documento</label>
                        <input type="text" name="id_number" class="form-control" value="{{ old('id_number', $client?->id_number) }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Teléfono</label>
                        <input type="text" name="phone" class="form-control" value="{{ old('phone', $client?->phone) }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control no-uppercase" value="{{ old('email', $client?->email) }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Fecha de nacimiento</label>
                        <input type="date" name="birthdate" class="form-control no-uppercase" value="{{ old('birthdate', optional($client?->birthdate)->toDateString()) }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Género</label>
                        <select name="gender" class="form-select">
                            <option value="">Sin especificar</option>
                            @foreach(['femenino' => 'Femenino', 'masculino' => 'Masculino', 'otro' => 'Otro'] as $val => $lbl)
                                <option value="{{ $val }}" {{ old('gender', $client?->gender) === $val ? 'selected' : '' }}>{{ $lbl }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">¿Cómo nos conoció?</label>
                        <input type="text" name="source" class="form-control" value="{{ old('source', $client?->source) }}" placeholder="Recomendación, redes sociales, etc.">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Dirección</label>
                        <input type="text" name="address" class="form-control" value="{{ old('address', $client?->address) }}">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Observaciones</label>
                        <textarea name="notes" rows="3" class="form-control" placeholder="Alergias, preferencias, notas...">{{ old('notes', $client?->notes) }}</textarea>
                    </div>
                    <div class="col-12">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="active" value="1" {{ old('active', $client?->active ?? true) ? 'checked' : '' }}>
                            <label class="form-check-label">Cliente activo</label>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="d-flex gap-2">
            <button class="btn btn-primary px-4" type="submit"><i class="bi bi-save"></i> {{ $client ? 'Guardar cambios' : 'Registrar cliente' }}</button>
            <a href="{{ route('clients.index') }}" class="btn btn-light border">Cancelar</a>
        </div>
    </form>
</div>
