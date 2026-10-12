@csrf
@if($method->exists) @method('PUT') @endif

<div class="row g-3">
    @if(auth()->user()->is_super_admin && !$method->exists)
        <div class="col-md-6">
            <label class="form-label">Empresa <span class="text-danger">*</span></label>
            <select name="company_id" class="form-select" required>
                <option value="">Seleccionar...</option>
                @foreach($companies as $c)
                    <option value="{{ $c->id }}" @selected(old('company_id') == $c->id)>{{ $c->name }}</option>
                @endforeach
            </select>
        </div>
    @endif

    <div class="col-md-6">
        <label class="form-label">Nombre <span class="text-danger">*</span></label>
        <input type="text" name="name" class="form-control" required maxlength="80"
               value="{{ old('name', $method->name) }}" placeholder="Ej. TARJETA DE DÉBITO">
    </div>

    <div class="col-md-6">
        <label class="form-label">Código</label>
        <input type="text" name="code" class="form-control no-uppercase" maxlength="40"
               value="{{ old('code', $method->code) }}" placeholder="Se genera del nombre">
        <small class="text-muted">Identificador interno con el que se guardan las ventas. Si lo dejas vacío se genera del nombre.</small>
    </div>

    <div class="col-md-6">
        <label class="form-label">Icono</label>
        <select name="icon" class="form-select">
            <option value="">Sin icono</option>
            @foreach($icons as $value => $label)
                <option value="{{ $value }}" @selected(old('icon', $method->icon) === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </div>

    <div class="col-md-3">
        <label class="form-label">Orden</label>
        <input type="number" name="sort_order" class="form-control no-uppercase" min="0" max="999"
               value="{{ old('sort_order', $method->sort_order ?? 0) }}">
        <small class="text-muted">Menor número aparece primero.</small>
    </div>

    <div class="col-md-3 d-flex align-items-end">
        <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" name="active" value="1" id="pm-active"
                   @checked(old('active', $method->active ?? true))>
            <label class="form-check-label" for="pm-active">Activo</label>
        </div>
    </div>

    <div class="col-md-6">
        <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" name="affects_cash" value="1" id="pm-cash"
                   @checked(old('affects_cash', $method->affects_cash ?? false))>
            <label class="form-check-label" for="pm-cash">Suma al efectivo de la caja</label>
        </div>
        <small class="text-muted">Actívalo solo para cobros en efectivo que se cuentan al cerrar el arqueo.</small>
    </div>

    <div class="col-md-6">
        <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" name="is_default" value="1" id="pm-default"
                   @checked(old('is_default', $method->is_default ?? false))>
            <label class="form-check-label" for="pm-default">Predeterminado en el punto de venta</label>
        </div>
        <small class="text-muted">Solo un método puede ser el predeterminado.</small>
    </div>
</div>

<hr class="my-4">

<div class="d-flex justify-content-end gap-2">
    <a href="{{ route('payment-methods.index') }}" class="btn btn-outline-secondary">Cancelar</a>
    <button class="btn btn-primary"><i class="bi bi-check-lg"></i> {{ $method->exists ? 'Guardar cambios' : 'Crear método' }}</button>
</div>
