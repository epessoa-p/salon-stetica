@extends('layouts.app')

@section('title', 'Categorías de gasto')

@section('page')
@php
    $authUser = auth()->user();
    $authCompany = $authUser->getCurrentCompany();
    $canCreate = $authUser->hasPermissionInCompany('expense_categories.create', $authCompany);
    $canEdit = $authUser->hasPermissionInCompany('expense_categories.edit', $authCompany);
    $canDelete = $authUser->hasPermissionInCompany('expense_categories.delete', $authCompany);
    $groupIcons = ['general' => 'bi-tag', 'basicos' => 'bi-house-gear', 'proveedor' => 'bi-truck', 'personal' => 'bi-person-badge'];
    $types = \App\Models\ExpenseCategory::TYPES;
    $newType = array_key_exists((string) request('new'), $types) ? request('new') : null;
@endphp
<div class="container-fluid" style="max-width: 760px;">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h1 class="mb-1"><i class="bi bi-tags"></i> Categorías de gasto</h1>
            <p class="text-muted mb-0">En qué se gasta. El tipo decide en qué pestaña de Gastos aparece.</p>
        </div>
        @if($canCreate && !$noCompany)
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#cat-new"><i class="bi bi-plus-circle"></i> Nueva categoría</button>
        @endif
    </div>

    @if($errors->any())<div class="alert alert-danger"><ul class="mb-0 ps-3">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

    @if($noCompany)
        <div class="alert alert-info d-flex align-items-center gap-2"><i class="bi bi-info-circle-fill"></i> Este módulo se gestiona dentro de una empresa.</div>
    @else
        @foreach(\App\Models\ExpenseCategory::GROUP_TITLES as $key => $title)
            @continue(!$categories->has($key))
            <h6 class="text-uppercase text-muted small fw-bold mt-4 mb-2" style="letter-spacing: 1px;">{{ $title }}</h6>
            @foreach($categories[$key] as $c)
                <div class="card border-0 shadow-sm mb-2 {{ $c->active ? '' : 'opacity-50' }}">
                    <div class="card-body py-2 d-flex justify-content-between align-items-center">
                        <div>
                            <i class="bi {{ $groupIcons[$key] }} text-primary me-2"></i>
                            <span class="fw-semibold">{{ $c->name }}</span>
                            @if($c->recurring)<span class="badge bg-light text-dark border">{{ \App\Models\ExpenseCategory::FREQUENCIES[$c->frequency] ?? 'Mensual' }}</span>@endif
                            @unless($c->active)<span class="badge bg-secondary">Inactiva</span>@endunless
                            <div class="small text-muted ms-4 ps-1">{{ $c->expenses_count }} {{ $c->expenses_count === 1 ? 'gasto' : 'gastos' }}</div>
                        </div>
                        <div class="d-flex gap-1">
                            @if($canEdit)
                                <button type="button" class="btn btn-sm btn-outline-secondary" title="Editar" data-bs-toggle="modal" data-bs-target="#cat-{{ $c->id }}"><i class="bi bi-pencil"></i></button>
                            @endif
                            @if($canDelete && $c->active)
                                <form action="{{ route('expense-categories.destroy', $c) }}" method="POST" onsubmit="return confirm('¿Eliminar esta categoría? Si tiene gastos, solo se da de baja.')">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-sm btn-outline-danger" title="Eliminar"><i class="bi bi-trash"></i></button>
                                </form>
                            @endif
                        </div>
                    </div>
                </div>
                @if($canEdit)
                    @include('finance.expense_categories.modal', ['modalId' => 'cat-' . $c->id, 'category' => $c, 'action' => route('expense-categories.update', $c), 'initialType' => $c->group()])
                @endif
            @endforeach
        @endforeach

        @if($canCreate)
            @include('finance.expense_categories.modal', ['modalId' => 'cat-new', 'category' => null, 'action' => route('expense-categories.store'), 'initialType' => $newType ?? 'general'])
            @if($newType)
                <script>document.addEventListener('DOMContentLoaded', () => bootstrap.Modal.getOrCreateInstance(document.getElementById('cat-new')).show());</script>
            @endif
        @endif
    @endif
</div>
<script>
    // Mensual / Anual solo aplica a los servicios básicos.
    document.querySelectorAll('[data-cat-type]').forEach((select) => {
        const box = select.closest('form').querySelector('[data-cat-frequency]');
        const sync = () => box.classList.toggle('d-none', select.value !== 'basicos');
        select.addEventListener('change', sync);
        sync();
    });
</script>
@endsection
