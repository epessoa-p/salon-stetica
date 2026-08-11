<div class="container-fluid" style="max-width: 1100px;">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h1 class="mb-1">{{ $personal ? 'Editar personal' : 'Nuevo personal' }}</h1>
            <p class="text-muted mb-0">Al crear personal se genera usuario automáticamente y se asigna rol según el cargo.</p>
        </div>
        <a href="{{ route('personal.index') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Volver</a>
    </div>

    @if($errors->any())
        <div class="alert alert-danger alert-dismissible fade show d-flex gap-2 align-items-start" role="alert">
            <i class="bi bi-exclamation-triangle-fill mt-1 flex-shrink-0"></i>
            <div>
                <strong>Por favor corrige los siguientes errores:</strong>
                <ul class="mb-0 mt-1 ps-3">
                    @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                </ul>
            </div>
            <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <form action="{{ $action }}" method="POST" class="row g-4">
        @csrf
        @if($method !== 'POST') @method($method) @endif

        <div class="col-lg-7">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-4">
                    <h6 class="fw-bold mb-3"><i class="bi bi-person-badge"></i> Datos del personal</h6>
                    <div class="row g-3">
                        @if($companies->count() > 1)
                            <div class="col-md-6">
                                <label class="form-label">Empresa</label>
                                <select name="company_id" id="company_id" class="form-select" required>
                                    <option value="">Seleccionar empresa</option>
                                    @foreach($companies as $company)
                                        <option value="{{ $company->id }}" {{ (string) old('company_id', $personal?->company_id) === (string) $company->id ? 'selected' : '' }}>{{ $company->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        @endif

                        <div class="col-md-6">
                            <label class="form-label">Cargo <span class="text-danger">*</span></label>
                            <select name="cargo_id" id="cargo_id" class="form-select" required
                                    data-selected="{{ old('cargo_id', $personal?->cargo_id) }}">
                                <option value="">{{ $companies->count() > 1 && $cargos->isEmpty() ? 'Selecciona primero una empresa' : 'Seleccionar cargo' }}</option>
                                @foreach($cargos as $cargo)
                                    <option value="{{ $cargo->id }}" {{ (string) old('cargo_id', $personal?->cargo_id) === (string) $cargo->id ? 'selected' : '' }}>
                                        {{ $cargo->name }} · Rol: {{ $cargo->role?->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-8">
                            <label class="form-label">Nombre completo <span class="text-danger">*</span></label>
                            <input type="text" name="full_name" class="form-control" value="{{ old('full_name', $personal?->full_name) }}" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Documento</label>
                            <input type="text" name="id_number" class="form-control" value="{{ old('id_number', $personal?->id_number) }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Teléfono</label>
                            <input type="text" name="phone" class="form-control" value="{{ old('phone', $personal?->phone) }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Fecha de ingreso</label>
                            <input type="date" name="hire_date" class="form-control" value="{{ old('hire_date', optional($personal?->hire_date)->toDateString()) }}">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Dirección</label>
                            <input type="text" name="address" class="form-control" value="{{ old('address', $personal?->address) }}">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Notas</label>
                            <textarea name="notes" rows="2" class="form-control">{{ old('notes', $personal?->notes) }}</textarea>
                        </div>
                        <div class="col-12">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="active" value="1" {{ old('active', $personal?->active ?? true) ? 'checked' : '' }}>
                                <label class="form-check-label">Activo</label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-4">
                    <h6 class="fw-bold mb-3"><i class="bi bi-person-gear"></i> Acceso al sistema</h6>

                    @php $alreadyHasUser = (bool) ($personal?->user); @endphp

                    @if($alreadyHasUser)
                        {{-- El personal ya tiene usuario: se editan sus credenciales --}}
                        <div class="alert alert-success small d-flex align-items-center gap-2">
                            <i class="bi bi-check-circle-fill"></i> Este personal tiene acceso al sistema.
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Email de acceso <span class="text-danger">*</span></label>
                            <input type="email" name="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email', $personal?->user?->email ?? $personal?->email) }}" required>
                            @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Contraseña <span class="text-muted small">(opcional, para cambiar)</span></label>
                            <input type="password" name="password" class="form-control @error('password') is-invalid @enderror">
                            @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Confirmar contraseña</label>
                            <input type="password" name="password_confirmation" class="form-control">
                        </div>
                    @else
                        {{-- Sin usuario: el acceso es opcional --}}
                        <p class="text-muted small mb-3">El personal puede registrarse sin acceso al sistema. Si necesita iniciar sesión, activa el acceso.</p>

                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" id="create_access" name="create_access" value="1" {{ old('create_access') ? 'checked' : '' }}>
                            <label class="form-check-label" for="create_access">Crear acceso al sistema</label>
                        </div>

                        <div id="access-fields" style="{{ old('create_access') ? '' : 'display:none;' }}">
                            <div class="alert alert-info small">El nombre de usuario se genera automáticamente con base en el nombre completo.</div>
                            <div class="mb-3">
                                <label class="form-label">Email de acceso <span class="text-danger">*</span></label>
                                <input type="email" name="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email', $personal?->email) }}">
                                @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Contraseña <span class="text-danger">*</span></label>
                                <input type="password" name="password" class="form-control @error('password') is-invalid @enderror">
                                @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Confirmar contraseña</label>
                                <input type="password" name="password_confirmation" class="form-control">
                            </div>
                        </div>
                    @endif

                    <button class="btn btn-primary w-100" type="submit">
                        <i class="bi bi-save"></i> {{ $personal ? 'Guardar cambios' : 'Crear personal' }}
                    </button>
                    <a href="{{ route('personal.index') }}" class="btn btn-light border w-100 mt-2">Cancelar</a>
                </div>
            </div>
        </div>
    </form>
</div>

@push('scripts')
<script>
    (function () {
        var chk = document.getElementById('create_access');
        var box = document.getElementById('access-fields');
        if (chk && box) {
            chk.addEventListener('change', function () {
                box.style.display = chk.checked ? '' : 'none';
            });
        }
    })();
</script>
@endpush

@if($companies->count() > 1)
@push('scripts')
<script>
$(function () {
    const $company = $('#company_id');
    const $cargo = $('#cargo_id');
    const urlTemplate = '{{ route('personal.cargos-by-company', ':id') }}';
    const preselect = $cargo.data('selected') ? String($cargo.data('selected')) : '';

    function loadCargos(companyId, keepSelected) {
        if (!companyId) {
            $cargo.empty().append(new Option('Selecciona primero una empresa', ''));
            return;
        }

        $cargo.prop('disabled', true).empty().append(new Option('Cargando cargos...', ''));

        $.getJSON(urlTemplate.replace(':id', companyId))
            .done(function (cargos) {
                $cargo.empty();
                if (!cargos.length) {
                    $cargo.append(new Option('Esta empresa no tiene cargos activos', ''));
                    return;
                }
                $cargo.append(new Option('Seleccionar cargo', ''));
                cargos.forEach(function (c) {
                    const opt = new Option(c.label, c.id);
                    if (keepSelected && String(c.id) === preselect) {
                        opt.selected = true;
                    }
                    $cargo.append(opt);
                });
            })
            .fail(function () {
                $cargo.empty().append(new Option('Error al cargar cargos', ''));
            })
            .always(function () {
                $cargo.prop('disabled', false);
            });
    }

    // Al cambiar de empresa se recargan los cargos de esa empresa.
    $company.on('change', function () {
        loadCargos($(this).val(), false);
    });
    // Nota: al cargar la página los cargos ya vienen renderizados desde el servidor
    // (empresa preseleccionada en edición o tras un error de validación), por lo que no se recargan aquí.
});
</script>
@endpush
@endif
