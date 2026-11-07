@extends('layouts.app')

@section('title', 'Gasto ' . $expense->number)

@section('page')
@php
    $authUser = auth()->user();
    $canCancel = $authUser->hasPermissionInCompany('expenses.delete', $authUser->getCurrentCompany()) && !$expense->isCancelled() && !$expense->isGenerated();
@endphp
<div class="container-fluid" style="max-width: 640px;">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <h1 class="mb-0"><i class="bi bi-receipt"></i> Gasto {{ $expense->number }}</h1>
        <a href="{{ route('expenses.index', array_filter(['group' => $expense->expenseCategory?->group()])) }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Volver</a>
    </div>

    @if($errors->any())<div class="alert alert-danger"><ul class="mb-0 ps-3">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <div class="fs-3 fw-bold {{ $expense->isCancelled() ? 'text-decoration-line-through text-muted' : '' }}">{{ $setting->money($expense->amount) }}</div>
            <div class="fw-semibold mb-3">{{ $expense->concept }}</div>
            <dl class="row mb-0 small">
                <dt class="col-4 text-muted fw-normal">Fecha</dt><dd class="col-8">{{ optional($expense->movement_date)->format('d/m/Y') }}</dd>
                <dt class="col-4 text-muted fw-normal">Categoría</dt><dd class="col-8">{{ $expense->expenseCategory?->name ?? ($expense->category ?: '—') }}</dd>
                @if($expense->supplierRef || $expense->personal)
                    <dt class="col-4 text-muted fw-normal">Pagado a</dt><dd class="col-8">{{ $expense->supplierRef?->name ?? $expense->personal?->full_name }}</dd>
                @endif
                <dt class="col-4 text-muted fw-normal">Salió de</dt>
                <dd class="col-8">{{ $expense->account?->name ?? ($expense->session ? 'Caja · ' . $expense->session->register?->name : 'Sin origen') }}</dd>
                @if($expense->reference)<dt class="col-4 text-muted fw-normal">Referencia</dt><dd class="col-8">{{ $expense->reference }}</dd>@endif
                @if($expense->notes)<dt class="col-4 text-muted fw-normal">Notas</dt><dd class="col-8">{{ $expense->notes }}</dd>@endif
                @if($expense->isCancelled())
                    <dt class="col-4 text-muted fw-normal">Anulado</dt>
                    <dd class="col-8">{{ $expense->cancelled_at->format('d/m/Y H:i') }}{{ $expense->cancel_reason ? ' · ' . $expense->cancel_reason : '' }}</dd>
                @endif
            </dl>
        </div>
    </div>

    @if($canCancel)
        <form action="{{ route('expenses.cancel', $expense) }}" method="POST" class="card border-0 shadow-sm" onsubmit="return confirm('¿Anular este gasto? El dinero vuelve a su origen.')">
            <div class="card-body">
                @csrf
                <label class="form-label small mb-1">Motivo de la anulación (opcional)</label>
                <input type="text" name="cancel_reason" class="form-control form-control-sm mb-2" maxlength="255">
                <button class="btn btn-outline-danger"><i class="bi bi-x-circle"></i> {{ $expense->financial_account_id ? 'Anular (vuelve a la cuenta)' : ($expense->cash_session_id ? 'Anular (vuelve a la caja)' : 'Anular') }}</button>
            </div>
        </form>
    @endif
</div>
@endsection
