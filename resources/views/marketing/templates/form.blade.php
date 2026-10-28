@extends('layouts.app')

@section('title', $template->exists ? 'Editar plantilla' : 'Nueva plantilla')

@section('page')
<div class="container-fluid" style="max-width: 980px;">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <h1 class="mb-0"><i class="bi bi-envelope-paper"></i> {{ $template->exists ? 'Editar plantilla' : 'Nueva plantilla' }}</h1>
        <a href="{{ route('marketing.templates.index') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Volver</a>
    </div>

    @if($errors->any())
        <div class="alert alert-danger"><ul class="mb-0 ps-3">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
    @endif

    <form action="{{ $template->exists ? route('marketing.templates.update', $template) : route('marketing.templates.store') }}" method="POST">
        @csrf
        @if($template->exists) @method('PUT') @endif
        <div class="row g-3">
            <div class="col-lg-7">
                <div class="card border-0 shadow-sm"><div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-5">
                            <label class="form-label" for="t-type">Tipo</label>
                            <select name="type" id="t-type" class="form-select">
                                @foreach(\App\Models\MessageTemplate::TYPES as $k => [$label])<option value="{{ $k }}" @selected(old('type', $template->type) === $k)>{{ $label }}</option>@endforeach
                            </select>
                        </div>
                        <div class="col-md-7">
                            <label class="form-label" for="t-name">Nombre <span class="text-danger">*</span></label>
                            <input type="text" name="name" id="t-name" class="form-control" maxlength="120" required value="{{ old('name', $template->name) }}">
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="t-body">Mensaje <span class="text-danger">*</span></label>
                            <textarea name="body" id="t-body" rows="6" class="form-control no-uppercase" maxlength="1000" required>{{ old('body', $template->body) }}</textarea>
                            <div class="d-flex flex-wrap gap-1 mt-2">
                                @foreach(\App\Models\MessageTemplate::VARIABLES as $k => $d)
                                    <button type="button" class="btn btn-light border btn-sm py-0" data-var="{{ $k }}" title="{{ $d }}">{{ '{' . $k . '}' }}</button>
                                @endforeach
                            </div>
                            <div class="form-text">Toca una variable para insertarla. Se reemplaza por el dato real al enviar.</div>
                        </div>
                    </div>
                    <div class="form-check form-switch mt-3">
                        <input class="form-check-input" type="checkbox" name="is_default" value="1" id="t-default" @checked(old('is_default', $template->is_default))>
                        <label class="form-check-label" for="t-default">Predeterminada para su tipo</label>
                    </div>
                    <div class="form-check form-switch">
                        <input type="hidden" name="active" value="0">
                        <input class="form-check-input" type="checkbox" name="active" value="1" id="t-active" @checked(old('active', $template->active ?? true))>
                        <label class="form-check-label" for="t-active">Activa</label>
                    </div>
                    <button class="btn btn-primary mt-3"><i class="bi bi-check-lg"></i> Guardar</button>
                </div></div>
            </div>
            <div class="col-lg-5">
                <div class="card border-0 shadow-sm" style="position: sticky; top: .75rem;"><div class="card-body" style="background: #ece5dd; border-radius: inherit;">
                    <div class="small text-muted mb-2">Vista previa (datos de ejemplo)</div>
                    <div id="t-preview" style="background: #dcf8c6; border-radius: 10px 10px 10px 2px; padding: .6rem .75rem; white-space: pre-line;"></div>
                </div></div>
            </div>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
(function () {
    const sample = @json($sample);
    const body = document.getElementById('t-body');
    const preview = () => {
        document.getElementById('t-preview').textContent = body.value.replace(/\{(\w+)\}/g, (m, k) => sample[k] ?? '');
    };
    document.querySelectorAll('[data-var]').forEach((b) => b.addEventListener('click', () => {
        const v = '{' + b.dataset.var + '}';
        const [s, e] = [body.selectionStart, body.selectionEnd];
        body.value = body.value.slice(0, s) + v + body.value.slice(e);
        body.focus();
        body.selectionStart = body.selectionEnd = s + v.length;
        preview();
    }));
    body.addEventListener('input', preview);
    preview();
})();
</script>
@endpush
