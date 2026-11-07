@extends('layouts.app')

@php
    $titles = ['general' => 'Nuevo gasto', 'proveedor' => 'Pago a proveedor', 'personal' => 'Pago a personal', 'basicos' => 'Pago de servicio'];
    $backParams = ['group' => $group];
@endphp

@section('title', $titles[$group])

@section('page')
<div class="container-fluid" style="max-width: 640px;">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <h1 class="mb-0"><i class="bi bi-arrow-up-circle"></i> {{ $titles[$group] }}</h1>
        <a href="{{ route('expenses.index', $backParams) }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Volver</a>
    </div>

    @if($errors->any())<div class="alert alert-danger"><ul class="mb-0 ps-3">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

    <div class="card border-0 shadow-sm">
        <div class="card-body p-4">
            <form action="{{ route('expenses.store') }}" method="POST">
                @csrf
                <input type="hidden" name="group" value="{{ $group }}">
                <div class="row g-3">
                    @if($group === 'proveedor')
                        <div class="col-12">
                            <label class="form-label">Proveedor <span class="text-danger">*</span></label>
                            <select name="supplier_id" class="form-select" required>
                                <option value="">Seleccionar...</option>
                                @foreach($suppliers as $s)<option value="{{ $s->id }}" @selected((string) old('supplier_id') === (string) $s->id)>{{ $s->name }}</option>@endforeach
                            </select>
                            @if($suppliers->isEmpty())<small class="text-muted">No hay proveedores. <a href="{{ route('suppliers.create') }}">Crea uno</a>.</small>@endif
                        </div>
                    @elseif($group === 'personal')
                        <div class="col-12">
                            <label class="form-label">Personal <span class="text-danger">*</span></label>
                            <select name="personal_id" class="form-select" required>
                                <option value="">Seleccionar...</option>
                                @foreach($people as $p)<option value="{{ $p->id }}" @selected((string) old('personal_id') === (string) $p->id)>{{ $p->full_name }}</option>@endforeach
                            </select>
                        </div>
                    @endif

                    {{-- En proveedor y personal va la categoría habitual, salvo que el grupo tenga varias. --}}
                    @if(in_array($group, ['proveedor', 'personal'], true) && $categories->count() <= 1)
                        <input type="hidden" name="expense_category_id" value="{{ $defaultCategory }}">
                    @else
                        <div class="col-12">
                            <label class="form-label">{{ $group === 'basicos' ? 'Servicio' : (in_array($group, ['proveedor', 'personal'], true) ? 'Concepto' : 'Categoría') }} <span class="text-danger">*</span></label>
                            <select name="expense_category_id" class="form-select" required>
                                <option value="">Seleccionar...</option>
                                @foreach($categories as $c)
                                    <option value="{{ $c->id }}" @selected((string) old('expense_category_id', $defaultCategory) === (string) $c->id)>{{ $c->name }}{{ $c->isAnnual() ? ' (anual)' : '' }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif

                    <div class="col-12">
                        <label class="form-label">Descripción @if($group === 'general')<span class="text-danger">*</span>@endif</label>
                        <input type="text" name="concept" class="form-control" maxlength="255" value="{{ old('concept') }}"
                               placeholder="{{ ['proveedor' => 'Pago a … (se completa solo)', 'personal' => 'Pago a … (se completa solo)', 'basicos' => 'Servicio · mes (se completa solo)'][$group] ?? 'Ej.: almuerzo del equipo' }}" @required($group === 'general')>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Importe <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text">{{ $setting->currency_symbol }}</span>
                            <input type="number" step="0.01" min="0.01" name="amount" id="exp-amount" class="form-control no-uppercase" value="{{ old('amount', $presetAmount) }}" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Fecha <span class="text-danger">*</span></label>
                        <input type="date" name="movement_date" class="form-control no-uppercase" value="{{ old('movement_date', now()->toDateString()) }}" max="{{ now()->toDateString() }}" required>
                    </div>
                    @if($branches->count() > 1)
                        <div class="col-12">
                            <label class="form-label">Sucursal</label>
                            <select name="branch_id" class="form-select">
                                <option value="">Según la caja o el usuario</option>
                                @foreach($branches as $b)<option value="{{ $b->id }}" @selected((string) old('branch_id') === (string) $b->id)>{{ $b->name }}</option>@endforeach
                            </select>
                        </div>
                    @endif
                    <div class="col-md-6">
                        <label class="form-label">Referencia</label>
                        <input type="text" name="reference" class="form-control no-uppercase" value="{{ old('reference') }}" placeholder="N.º de factura">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Notas</label>
                        <input type="text" name="notes" class="form-control" value="{{ old('notes') }}">
                    </div>
                    <div class="col-12">
                        @include('finance.partials.payment_source', ['sources' => $sources, 'label' => 'Sale de', 'amountInput' => '#exp-amount', 'selectedAccount' => $selectedAccount])
                    </div>
                </div>

                <div class="mt-4 d-flex gap-2">
                    <button class="btn btn-primary px-4"><i class="bi bi-save"></i> Registrar</button>
                    <a href="{{ route('expenses.index', $backParams) }}" class="btn btn-light border">Cancelar</a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
