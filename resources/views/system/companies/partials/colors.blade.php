<div class="form-group mb-3">
    <label class="form-label d-block">Colores base</label>
    <small class="text-muted d-block mb-2">
        Se usan en el menú de navegación (color secundario) y en la cabecera / barra superior (color primario).
    </small>

    <div class="row g-3">
        <div class="col-md-6">
            <label for="primary_color" class="form-label small fw-semibold">Color primario (cabecera)</label>
            <div class="input-group">
                <input type="color" class="form-control form-control-color js-color-picker" data-target="primary_color_text"
                       value="{{ $primaryColor }}" title="Color de la cabecera">
                <input type="text" id="primary_color_text" name="primary_color"
                       class="form-control no-uppercase @error('primary_color') is-invalid @enderror"
                       value="{{ $primaryColor }}" maxlength="7" placeholder="#1e1e1e">
            </div>
            @error('primary_color')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
        </div>

        <div class="col-md-6">
            <label for="secondary_color" class="form-label small fw-semibold">Color secundario (menú)</label>
            <div class="input-group">
                <input type="color" class="form-control form-control-color js-color-picker" data-target="secondary_color_text"
                       value="{{ $secondaryColor }}" title="Color del menú de navegación">
                <input type="text" id="secondary_color_text" name="secondary_color"
                       class="form-control no-uppercase @error('secondary_color') is-invalid @enderror"
                       value="{{ $secondaryColor }}" maxlength="7" placeholder="#f8f8f8">
            </div>
            @error('secondary_color')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
        </div>
    </div>
</div>

@push('scripts')
<script>
    document.querySelectorAll('.js-color-picker').forEach(function (picker) {
        var text = document.getElementById(picker.dataset.target);
        if (!text) return;

        // Color -> texto
        picker.addEventListener('input', function () {
            text.value = picker.value;
        });

        // Texto -> color (solo si es un hex válido de 6 dígitos)
        text.addEventListener('input', function () {
            if (/^#[0-9A-Fa-f]{6}$/.test(text.value)) {
                picker.value = text.value;
            }
        });
    });
</script>
@endpush
