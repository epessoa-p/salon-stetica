@extends('layouts.app')

@section('title', 'Abrir caja')

@section('page')
<div class="container-fluid" style="max-width: 600px;">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h1 class="mb-1"><i class="bi bi-unlock"></i> Abrir caja</h1>
            <p class="text-muted mb-0">Inicia una sesión de caja con el monto de apertura.</p>
        </div>
        <a href="{{ route('cash-sessions.index') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Volver</a>
    </div>

    @if($errors->any())
        <div class="alert alert-danger"><ul class="mb-0 ps-3">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
    @endif

    <div class="card border-0 shadow-sm">
        <div class="card-body p-4">
            <form action="{{ route('cash-sessions.store') }}" method="POST">
                @csrf
                <div class="mb-3">
                    <label class="form-label">Caja <span class="text-danger">*</span></label>
                    <select name="cash_register_id" class="form-select" required>
                        <option value="">Seleccionar caja</option>
                        @foreach($registers as $reg)
                            @php $isOpen = in_array($reg->id, $openRegisterIds); @endphp
                            <option value="{{ $reg->id }}" {{ $isOpen ? 'disabled' : '' }} {{ (string) old('cash_register_id') === (string) $reg->id ? 'selected' : '' }}>
                                {{ $reg->name }} {{ $isOpen ? '(ya abierta)' : '' }}
                            </option>
                        @endforeach
                    </select>
                    @if($registers->isEmpty())<small class="text-muted">No hay cajas activas. <a href="{{ route('cajas.create') }}">Crea una</a>.</small>@endif
                </div>
                <div class="mb-3">
                    <label class="form-label">Monto de apertura <span class="text-danger">*</span></label>
                    <input type="number" step="0.01" min="0" name="opening_amount" class="form-control no-uppercase" value="{{ old('opening_amount', '0.00') }}" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Notas</label>
                    <textarea name="notes" rows="2" class="form-control">{{ old('notes') }}</textarea>
                </div>
                <button class="btn btn-primary px-4" type="submit"><i class="bi bi-unlock"></i> Abrir caja</button>
                <a href="{{ route('cash-sessions.index') }}" class="btn btn-light border">Cancelar</a>
            </form>
        </div>
    </div>
</div>
@endsection
