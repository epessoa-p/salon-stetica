@extends('layouts.app')

@section('page')
<h1><i class="bi bi-plus-circle"></i> Nuevo Rol</h1>

<div class="card mt-4">
    <div class="card-body">
        <form action="{{ route('roles.store') }}" method="POST">
            @csrf

            <div class="form-group mb-3">
                <label for="name" class="form-label">Nombre</label>
                <input type="text" id="name" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name') }}" required>
            </div>

            <div class="form-group mb-3">
                <label for="slug" class="form-label">Slug</label>
                <input type="text" id="slug" name="slug" class="form-control @error('slug') is-invalid @enderror" value="{{ old('slug') }}" required>
            </div>

            <div class="form-group mb-3">
                <label for="description" class="form-label">Descripción</label>
                <textarea id="description" name="description" class="form-control @error('description') is-invalid @enderror" rows="2">{{ old('description') }}</textarea>
            </div>

            <div class="form-group mb-3">
                <label class="form-label">Permisos</label>
                <div class="form-check-group" style="max-height: 400px; overflow-y: auto; border: 1px solid #ddd; padding: 10px; border-radius: 5px;">
                    @foreach($permissions as $module => $modulePerms)
                        <div class="mb-3 permission-module">
                            <div class="d-flex justify-content-between align-items-center border-bottom pb-1 mb-2">
                                <h6 class="text-capitalize mb-0">{{ $module }}</h6>
                                <div class="form-check mb-0">
                                    <input class="form-check-input check-all-module no-uppercase" type="checkbox" id="checkall_{{ $module }}">
                                    <label class="form-check-label small text-muted" for="checkall_{{ $module }}">Marcar todos</label>
                                </div>
                            </div>
                            @foreach($modulePerms as $permission)
                                <div class="form-check">
                                    <input class="form-check-input permission-check" type="checkbox" name="permissions[]" value="{{ $permission->id }}" id="perm_{{ $permission->id }}" {{ in_array($permission->id, old('permissions', [])) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="perm_{{ $permission->id }}">
                                        {{ $permission->name }}
                                    </label>
                                </div>
                            @endforeach
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="form-group">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check-circle"></i> Crear Rol
                </button>
                <a href="{{ route('roles.index') }}" class="btn btn-secondary">
                    <i class="bi bi-arrow-left"></i> Cancelar
                </a>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
    document.querySelectorAll('.permission-module').forEach(function (group) {
        var master = group.querySelector('.check-all-module');
        var items = group.querySelectorAll('.permission-check');
        if (!master) return;

        function syncMaster() {
            var total = items.length;
            var checked = group.querySelectorAll('.permission-check:checked').length;
            master.checked = checked === total && total > 0;
            master.indeterminate = checked > 0 && checked < total;
        }

        master.addEventListener('change', function () {
            items.forEach(function (item) { item.checked = master.checked; });
            master.indeterminate = false;
        });

        items.forEach(function (item) { item.addEventListener('change', syncMaster); });
        syncMaster();
    });
</script>
@endpush
@endsection
