<div class="container-fluid" style="max-width: 1100px;">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h1 class="mb-1">{{ $cargo ? 'Editar cargo' : 'Nuevo cargo' }}</h1>
            <p class="text-muted mb-0">Asocia un cargo a un rol para heredar permisos automáticamente.</p>
        </div>
        <a href="{{ route('cargos.index') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Volver</a>
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

        {{-- Datos del cargo: fila superior a todo el ancho --}}
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-4">
                    <h6 class="fw-bold mb-3"><i class="bi bi-briefcase"></i> Datos del cargo</h6>
                    <div class="row g-3">
                        @if($companies->count() > 1)
                            <div class="col-md-4">
                                <label class="form-label">Empresa</label>
                                <select name="company_id" class="form-select" required>
                                    <option value="">Seleccionar empresa</option>
                                    @foreach($companies as $company)
                                        <option value="{{ $company->id }}" {{ (string) old('company_id', $cargo?->company_id) === (string) $company->id ? 'selected' : '' }}>{{ $company->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        @endif

                        <div class="{{ $companies->count() > 1 ? 'col-md-8' : 'col-12' }}">
                            <label class="form-label">Nombre del cargo <span class="text-danger">*</span></label>
                            <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $cargo?->name) }}" required>
                            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-12">
                            <label class="form-label">Descripción</label>
                            <textarea name="description" rows="2" class="form-control" placeholder="Funciones principales del cargo...">{{ old('description', $cargo?->description) }}</textarea>
                        </div>

                        <div class="col-12">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="active" value="1" {{ old('active', $cargo?->active ?? true) ? 'checked' : '' }}>
                                <label class="form-check-label">Activo</label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Rol asociado (solo al editar; al crear siempre es rol nuevo) --}}
        @if($cargo)
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-4">
                    <h6 class="fw-bold mb-3"><i class="bi bi-shield-lock"></i> Rol asociado</h6>

                    <div class="btn-group w-100 mb-3" role="group">
                        <input type="radio" class="btn-check" name="role_mode" id="mode_existing" value="existing"
                            {{ old('role_mode', 'existing') === 'existing' ? 'checked' : '' }} autocomplete="off">
                        <label class="btn btn-outline-primary" for="mode_existing">
                            <i class="bi bi-list-check me-1"></i> Rol existente
                        </label>

                        <input type="radio" class="btn-check" name="role_mode" id="mode_new" value="new"
                            {{ old('role_mode') === 'new' ? 'checked' : '' }} autocomplete="off">
                        <label class="btn btn-outline-primary" for="mode_new">
                            <i class="bi bi-plus-circle me-1"></i> Crear nuevo rol
                        </label>
                    </div>

                    {{-- Existing role --}}
                    <div id="existing-role-section">
                        <label class="form-label">Seleccionar rol</label>
                        <select name="role_id" id="role_id" class="form-select @error('role_id') is-invalid @enderror">
                            <option value="">Seleccionar rol...</option>
                            @foreach($roles as $role)
                                <option value="{{ $role->id }}" {{ (string) old('role_id', $cargo?->role_id) === (string) $role->id ? 'selected' : '' }}>
                                    {{ $role->name }} ({{ $role->slug }})
                                </option>
                            @endforeach
                        </select>
                        @error('role_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        <small class="text-muted">Los permisos del rol serán heredados por el personal con este cargo.</small>
                    </div>

                    {{-- New role --}}
                    <div id="new-role-section" class="d-none">
                        <label class="form-label">Nombre del nuevo rol <span class="text-danger">*</span></label>
                        <input type="text" name="new_role_name" id="new_role_name" class="form-control @error('new_role_name') is-invalid @enderror"
                            value="{{ old('new_role_name') }}" placeholder="Ej: Supervisor">
                        @error('new_role_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        <small class="text-muted">El slug se generará automáticamente.</small>
                    </div>
                </div>
            </div>
        </div>
        @else
            {{-- Al crear, el cargo genera automáticamente su propio rol (mismo nombre del cargo) --}}
            <input type="hidden" name="role_mode" value="new">
        @endif

        {{-- Permisos: ancho completo --}}
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="fw-bold mb-0"><i class="bi bi-key"></i> Permisos del rol</h6>
                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-sm btn-outline-primary" id="btn-select-all">
                                <i class="bi bi-check-all"></i> Todos
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="btn-deselect-all">
                                <i class="bi bi-x-lg"></i> Ninguno
                            </button>
                        </div>
                    </div>
                    <div class="alert alert-info small py-2 mb-3">
                        <i class="bi bi-info-circle me-1"></i>
                        <span id="permissions-info">{{ $cargo ? 'Selecciona un rol o crea uno nuevo para configurar permisos.' : 'Marca los permisos que tendrá el rol de este cargo.' }}</span>
                    </div>

                    <div class="row g-3" id="permissions-container">
                        @foreach($permissions as $module => $modulePerms)
                            <div class="col-sm-6 col-lg-4">
                                <div class="border rounded-3 p-3 h-100 perm-module">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <h6 class="text-uppercase text-muted small fw-bold mb-0">
                                            <i class="bi bi-folder me-1"></i> {{ ucfirst($module) }}
                                        </h6>
                                        <div class="form-check mb-0">
                                            <input class="form-check-input check-all-module" type="checkbox" id="checkall_{{ $module }}">
                                            <label class="form-check-label small text-muted" for="checkall_{{ $module }}">Todos</label>
                                        </div>
                                    </div>
                                    @foreach($modulePerms as $permission)
                                        <div class="form-check mb-1">
                                            <input class="form-check-input perm-check" type="checkbox" name="permissions[]"
                                                value="{{ $permission->id }}" id="perm_{{ $permission->id }}"
                                                {{ in_array($permission->id, old('permissions', $cargo?->role?->permissions?->pluck('id')->toArray() ?? [])) ? 'checked' : '' }}>
                                            <label class="form-check-label small" for="perm_{{ $permission->id }}">
                                                {{ $permission->name }}
                                            </label>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div class="mt-3 text-muted small">
                        <i class="bi bi-shield-check me-1"></i>
                        <span id="perm-count">{{ count(old('permissions', $cargo?->role?->permissions?->pluck('id')->toArray() ?? [])) }}</span> permisos seleccionados
                    </div>
                </div>
            </div>
        </div>

        {{-- Botones --}}
        <div class="col-12 d-flex gap-2">
            <button class="btn btn-primary px-4" type="submit"><i class="bi bi-save"></i> {{ $cargo ? 'Guardar cambios' : 'Crear cargo' }}</button>
            <a href="{{ route('cargos.index') }}" class="btn btn-light border">Cancelar</a>
        </div>
    </form>
</div>

@push('scripts')
<script>
$(function () {
    const $roleSelect = $('#role_id');
    const $existingSection = $('#existing-role-section');
    const $newSection = $('#new-role-section');
    const $modeExisting = $('#mode_existing');
    const $modeNew = $('#mode_new');
    const $permChecks = $('.perm-check');
    const $permCount = $('#perm-count');
    const $permInfo = $('#permissions-info');
    const permissionsUrl = '{{ route("cargos.role-permissions", ":id") }}';

    const hasRoleCard = $existingSection.length > 0;

    function toggleMode() {
        const isNew = $modeNew.is(':checked');
        if (isNew) {
            $existingSection.addClass('d-none');
            $newSection.removeClass('d-none');
            $permInfo.text('Configura los permisos para el nuevo rol.');
        } else {
            $existingSection.removeClass('d-none');
            $newSection.addClass('d-none');
            const roleId = $roleSelect.val();
            $permInfo.text(roleId ? 'Permisos del rol seleccionado (puedes modificarlos).' : 'Selecciona un rol para ver sus permisos.');
        }
    }

    if (hasRoleCard) {
        $('input[name="role_mode"]').on('change', toggleMode);
        toggleMode();
    }

    function updateMasters() {
        $('.perm-module').each(function () {
            const $module = $(this);
            const $items = $module.find('.perm-check');
            const $master = $module.find('.check-all-module');
            const total = $items.length;
            const checked = $items.filter(':checked').length;
            $master.prop('checked', total > 0 && checked === total);
            $master.prop('indeterminate', checked > 0 && checked < total);
        });
    }

    function updateCount() {
        $permCount.text($permChecks.filter(':checked').length);
        updateMasters();
    }
    $permChecks.on('change', updateCount);

    // "Marcar todos" por recuadro (módulo)
    $('.check-all-module').on('change', function () {
        $(this).closest('.perm-module').find('.perm-check').prop('checked', this.checked);
        updateCount();
    });

    $('#btn-select-all').on('click', function () {
        $permChecks.prop('checked', true);
        updateCount();
    });
    $('#btn-deselect-all').on('click', function () {
        $permChecks.prop('checked', false);
        updateCount();
    });

    // Load permissions when selecting an existing role
    $roleSelect.on('change', function () {
        const roleId = $(this).val();
        if (!roleId) {
            $permInfo.text('Selecciona un rol para ver sus permisos.');
            return;
        }

        $permInfo.text('Cargando permisos...');

        $.getJSON(permissionsUrl.replace(':id', roleId))
            .done(function (permIds) {
                $permChecks.prop('checked', false);
                permIds.forEach(function (id) {
                    $('#perm_' + id).prop('checked', true);
                });
                updateCount();
                $permInfo.text('Permisos del rol seleccionado (puedes modificarlos).');
            })
            .fail(function () {
                $permInfo.text('Error al cargar permisos.');
            });
    });

    updateCount();
});
</script>
@endpush
