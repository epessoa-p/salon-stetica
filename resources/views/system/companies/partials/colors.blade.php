@php
    $defaultPrimary = \App\Models\Company::DEFAULT_PRIMARY_COLOR;
    $defaultSecondary = \App\Models\Company::DEFAULT_SECONDARY_COLOR;
@endphp
<div class="form-group mb-3">
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
        <div>
            <label class="form-label d-block mb-0">Colores base</label>
            <small class="text-muted d-block mb-2">
                Se usan en el menú de navegación (color secundario) y en la cabecera / barra superior (color primario).
            </small>
        </div>
        <button type="button" class="btn btn-sm btn-outline-secondary js-color-reset"
                data-primary="{{ $defaultPrimary }}" data-secondary="{{ $defaultSecondary }}"
                title="Vuelve a los colores de Glamora; se aplican al guardar">
            <i class="bi bi-arrow-counterclockwise"></i> Restaurar colores por defecto
        </button>
    </div>

    <div class="row g-3">
        <div class="col-md-6">
            <label for="primary_color" class="form-label small fw-semibold">Color primario (cabecera)</label>
            <div class="input-group">
                <input type="color" class="form-control form-control-color js-color-picker" data-target="primary_color_text"
                       value="{{ $primaryColor }}" title="Color de la cabecera">
                <input type="text" id="primary_color_text" name="primary_color"
                       class="form-control no-uppercase @error('primary_color') is-invalid @enderror"
                       value="{{ $primaryColor }}" maxlength="7" placeholder="{{ $defaultPrimary }}">
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
                       value="{{ $secondaryColor }}" maxlength="7" placeholder="{{ $defaultSecondary }}">
            </div>
            @error('secondary_color')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
        </div>
    </div>

    {{-- Vista previa: cabecera y menú con los colores elegidos --}}
    <div class="color-preview mt-3" aria-hidden="true">
        <div class="color-preview-header js-preview-header">Cabecera</div>
        <div class="d-flex">
            <div class="color-preview-nav js-preview-nav">Menú</div>
            <div class="color-preview-body">Contenido</div>
        </div>
    </div>
    <small class="text-muted js-color-reset-hint d-none"><i class="bi bi-info-circle"></i> Colores por defecto cargados: guarda para aplicarlos.</small>
</div>

@push('styles')
<style>
    .color-preview { border: 1px solid #e5e7eb; border-radius: 10px; overflow: hidden; max-width: 360px; font-size: .75rem; }
    .color-preview-header { padding: .45rem .7rem; font-weight: 600; }
    .color-preview-nav { width: 38%; padding: .6rem .7rem; min-height: 64px; }
    .color-preview-body { flex: 1; padding: .6rem .7rem; background: #fff; color: #6b7280; }
</style>
@endpush

@push('scripts')
<script>
    (function () {
        var valid = function (v) { return /^#[0-9A-Fa-f]{6}$/.test(v); };
        // Texto legible sobre un fondo (misma regla que el layout).
        var contrast = function (hex) {
            var r = parseInt(hex.substr(1, 2), 16), g = parseInt(hex.substr(3, 2), 16), b = parseInt(hex.substr(5, 2), 16);
            return (0.299 * r + 0.587 * g + 0.114 * b) / 255 > 0.6 ? '#1b1b1b' : '#ffffff';
        };
        var preview = function () {
            var p = document.getElementById('primary_color_text'), s = document.getElementById('secondary_color_text');
            var h = document.querySelector('.js-preview-header'), n = document.querySelector('.js-preview-nav');
            if (p && h && valid(p.value)) { h.style.background = p.value; h.style.color = contrast(p.value); }
            if (s && n && valid(s.value)) { n.style.background = s.value; n.style.color = contrast(s.value); }
        };

        document.querySelectorAll('.js-color-picker').forEach(function (picker) {
            var text = document.getElementById(picker.dataset.target);
            if (!text) return;

            // Color -> texto
            picker.addEventListener('input', function () {
                text.value = picker.value;
                preview();
            });

            // Texto -> color (solo si es un hex válido de 6 dígitos)
            text.addEventListener('input', function () {
                if (valid(text.value)) {
                    picker.value = text.value;
                }
                preview();
            });
        });

        // Restaurar: carga los colores por defecto en el formulario (se aplican al guardar).
        document.querySelectorAll('.js-color-reset').forEach(function (btn) {
            btn.addEventListener('click', function () {
                [['primary_color_text', btn.dataset.primary], ['secondary_color_text', btn.dataset.secondary]].forEach(function (pair) {
                    var text = document.getElementById(pair[0]);
                    if (!text) return;
                    text.value = pair[1];
                    var picker = document.querySelector('.js-color-picker[data-target="' + pair[0] + '"]');
                    if (picker) picker.value = pair[1].toLowerCase();
                });
                preview();
                var hint = document.querySelector('.js-color-reset-hint');
                if (hint) hint.classList.remove('d-none');
            });
        });

        preview();
    })();
</script>
@endpush
