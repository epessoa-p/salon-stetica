{{-- Alta o edición de una categoría. Recibe: modalId, category, action, initialType. --}}
<div class="modal fade" id="{{ $modalId }}" tabindex="-1">
    <div class="modal-dialog">
        <form action="{{ $action }}" method="POST" class="modal-content">
            @csrf
            @if($category) @method('PUT') @endif
            <div class="modal-header">
                <h5 class="modal-title">{{ $category ? 'Editar categoría' : ($initialType === 'basicos' ? 'Nuevo servicio' : 'Nueva categoría') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <label class="form-label small mb-1">Nombre</label>
                <input type="text" name="name" class="form-control mb-3" maxlength="120" required value="{{ $category?->name }}">
                <label class="form-label small mb-1">Tipo</label>
                <select name="type" class="form-select mb-3" data-cat-type>
                    @foreach(\App\Models\ExpenseCategory::TYPES as $code => $label)
                        <option value="{{ $code }}" @selected($initialType === $code)>{{ $label }}</option>
                    @endforeach
                </select>
                <div data-cat-frequency class="mb-3">
                    <label class="form-label small mb-1">Se paga</label>
                    <select name="frequency" class="form-select">
                        <option value="mensual" @selected(($category?->frequency ?? 'mensual') === 'mensual')>Mensual: queda pagado si hubo un pago en el mes (luz, agua, internet…)</option>
                        <option value="anual" @selected($category?->frequency === 'anual')>Anual: queda pagado por un año desde el último pago</option>
                    </select>
                </div>
                @if($category)
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" role="switch" name="active" value="1" id="{{ $modalId }}-active" @checked($category->active)>
                        <label class="form-check-label" for="{{ $modalId }}-active">Activa</label>
                    </div>
                @endif
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Cancelar</button>
                <button class="btn btn-primary"><i class="bi bi-save"></i> Guardar</button>
            </div>
        </form>
    </div>
</div>
