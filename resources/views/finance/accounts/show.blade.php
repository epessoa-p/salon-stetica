@extends('layouts.app')

@section('title', $account->name)

@section('page')
@php
    $authUser = auth()->user();
    $authCompany = $authUser->getCurrentCompany();
    $canMove = $authUser->hasPermissionInCompany('accounts.move', $authCompany);
    $canEdit = $authUser->hasPermissionInCompany('accounts.edit', $authCompany);
    $canDelete = $authUser->hasPermissionInCompany('accounts.delete', $authCompany);
    $canExpense = $authUser->hasPermissionInCompany('expenses.create', $authCompany);
@endphp
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-start mb-3 flex-wrap gap-2">
        <div>
            <h1 class="mb-1"><i class="bi {{ $account->icon() }}"></i> {{ $account->name }}</h1>
            <p class="text-muted mb-0">
                {{ $account->typeLabel() }}{{ $account->bank_name ? ' · ' . $account->bank_name : '' }}{{ $account->account_number ? ' · ' . $account->account_number : '' }}
                @unless($account->active)<span class="badge bg-secondary">Inactiva</span>@endunless
            </p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a href="{{ route('accounts.index') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Volver</a>
            @if($canEdit)<a href="{{ route('accounts.edit', $account) }}" class="btn btn-outline-primary"><i class="bi bi-pencil"></i> Editar</a>@endif
            @if($canDelete && $movements->isEmpty() && !array_filter($filters))
                <form action="{{ route('accounts.destroy', $account) }}" method="POST" onsubmit="return confirm('¿Eliminar esta cuenta?')">
                    @csrf
                    @method('DELETE')
                    <button class="btn btn-outline-danger"><i class="bi bi-trash"></i> Eliminar</button>
                </form>
            @endif
        </div>
    </div>

    @if($errors->any())<div class="alert alert-danger"><ul class="mb-0 ps-3">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div>
                <div class="text-muted small">Saldo</div>
                <div class="fs-3 fw-bold">{{ $setting->money($balance) }}</div>
            </div>
            @if($account->active)
                <div class="d-flex gap-2 flex-wrap">
                    @if($canExpense)<a href="{{ route('expenses.create', ['account' => $account->id]) }}" class="btn btn-danger"><i class="bi bi-arrow-up-circle"></i> Gasto</a>@endif
                    @if($canMove)
                        <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#m-contribute"><i class="bi bi-plus-circle"></i> Aporte</button>
                        <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#m-transfer"><i class="bi bi-arrow-left-right"></i> Traspaso</button>
                        <button type="button" class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#m-adjust"><i class="bi bi-sliders"></i> Ajuste</button>
                    @endif
                </div>
            @endif
        </div>
    </div>

    <form method="GET" class="card border-0 shadow-sm mb-3">
        <div class="card-body py-3">
            <div class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label small mb-1">Desde</label>
                    <input type="date" name="from" value="{{ $filters['from'] }}" class="form-control no-uppercase">
                </div>
                <div class="col-md-3">
                    <label class="form-label small mb-1">Hasta</label>
                    <input type="date" name="to" value="{{ $filters['to'] }}" class="form-control no-uppercase">
                </div>
                <div class="col-md-3 d-flex gap-2">
                    <button class="btn btn-outline-primary"><i class="bi bi-funnel"></i> Filtrar</button>
                    <a href="{{ route('accounts.show', $account) }}" class="btn btn-light border">Limpiar</a>
                </div>
            </div>
        </div>
    </form>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3">Fecha</th>
                            <th>Movimiento</th>
                            <th class="text-end">Entrada</th>
                            <th class="text-end">Salida</th>
                            <th class="text-end">Saldo</th>
                            <th class="text-end pe-3"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($movements as $m)
                            <tr class="{{ $m->isVoided() ? 'opacity-50' : '' }}">
                                <td class="ps-3 text-nowrap">{{ $m->movement_date->format('d/m/Y') }}</td>
                                <td>
                                    <div class="fw-semibold {{ $m->isVoided() ? 'text-decoration-line-through' : '' }}">{{ $m->concept }}</div>
                                    <small class="text-muted">{{ $m->typeLabel() }}{{ $m->category && $m->type === 'gasto' ? ' · ' . $m->category : '' }}{{ $m->creator ? ' · ' . $m->creator->name : '' }}</small>
                                    @if($m->isVoided())<span class="badge bg-secondary">Anulado {{ $m->voided_at->format('d/m/Y') }}</span>@endif
                                </td>
                                <td class="text-end text-success fw-semibold">{{ $m->isIn() ? number_format($m->amount, 2) : '' }}</td>
                                <td class="text-end text-danger fw-semibold">{{ $m->isIn() ? '' : number_format($m->amount, 2) }}</td>
                                <td class="text-end">
                                    @unless($m->isVoided())
                                        {{ number_format($running, 2) }}
                                        @php $running -= $m->signedAmount(); @endphp
                                    @endunless
                                </td>
                                <td class="text-end pe-3">
                                    @if($canMove && !$m->isVoided())
                                        <form action="{{ route('accounts.movements.void', [$account, $m]) }}" method="POST" class="d-inline" onsubmit="return confirm('¿Anular este movimiento?')">
                                            @csrf
                                            <button class="btn btn-sm btn-outline-danger" title="Anular"><i class="bi bi-x-lg"></i></button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center py-5 text-muted">No hay movimientos.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer bg-white">{{ $movements->links() }}</div>
    </div>
</div>

@if($canMove && $account->active)
    {{-- Aporte --}}
    <div class="modal fade" id="m-contribute" tabindex="-1">
        <div class="modal-dialog">
            <form action="{{ route('accounts.contribute', $account) }}" method="POST" class="modal-content">
                @csrf
                <div class="modal-header"><h5 class="modal-title">Aporte a {{ $account->name }}</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button></div>
                <div class="modal-body">
                    <label class="form-label small mb-1">Monto</label>
                    <div class="input-group mb-2"><span class="input-group-text">{{ $setting->currency_symbol }}</span><input type="number" name="amount" class="form-control text-end no-uppercase" min="0.01" step="0.01" required></div>
                    <label class="form-label small mb-1">Fecha</label>
                    <input type="date" name="movement_date" class="form-control form-control-sm mb-2 no-uppercase" value="{{ now()->toDateString() }}" max="{{ now()->toDateString() }}" required>
                    <label class="form-label small mb-1">Concepto</label>
                    <input type="text" name="concept" class="form-control form-control-sm mb-3" maxlength="255" placeholder="Aporte">
                    @include('finance.partials.payment_source', ['sources' => ['sessions' => $sources['sessions'], 'accounts' => $others, 'canUseAccounts' => $sources['canUseAccounts']], 'label' => 'Viene de', 'noneLabel' => 'Externo (dueño o socio)'])
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-light border" data-bs-dismiss="modal">Cancelar</button><button class="btn btn-success">Registrar aporte</button></div>
            </form>
        </div>
    </div>

    {{-- Traspaso --}}
    <div class="modal fade" id="m-transfer" tabindex="-1">
        <div class="modal-dialog">
            <form action="{{ route('accounts.transfer', $account) }}" method="POST" class="modal-content">
                @csrf
                <div class="modal-header"><h5 class="modal-title">Traspaso desde {{ $account->name }}</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button></div>
                <div class="modal-body">
                    <p class="small text-muted">El dinero cambia de lugar: no cuenta como gasto ni como ingreso.</p>
                    <label class="form-label small mb-1">Monto (saldo: {{ number_format($balance, 2) }})</label>
                    <div class="input-group mb-2"><span class="input-group-text">{{ $setting->currency_symbol }}</span><input type="number" name="amount" class="form-control text-end no-uppercase" min="0.01" step="0.01" max="{{ $balance }}" required></div>
                    <label class="form-label small mb-1">Hacia</label>
                    <select name="destination" class="form-select form-select-sm mb-2" required>
                        <option value="">Seleccionar...</option>
                        @if($others->isNotEmpty())
                            <optgroup label="Cuentas">@foreach($others as $o)<option value="cuenta:{{ $o->id }}">{{ $o->name }}</option>@endforeach</optgroup>
                        @endif
                        @if($sources['sessions']->isNotEmpty())
                            <optgroup label="Cajas abiertas">@foreach($sources['sessions'] as $s)<option value="caja:{{ $s->id }}">{{ $s->register?->name ?? 'Caja' }}</option>@endforeach</optgroup>
                        @endif
                    </select>
                    <label class="form-label small mb-1">Fecha</label>
                    <input type="date" name="movement_date" class="form-control form-control-sm mb-2 no-uppercase" value="{{ now()->toDateString() }}" max="{{ now()->toDateString() }}" required>
                    <label class="form-label small mb-1">Concepto (opcional)</label>
                    <input type="text" name="concept" class="form-control form-control-sm" maxlength="255">
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-light border" data-bs-dismiss="modal">Cancelar</button><button class="btn btn-primary">Registrar traspaso</button></div>
            </form>
        </div>
    </div>

    {{-- Ajuste --}}
    <div class="modal fade" id="m-adjust" tabindex="-1">
        <div class="modal-dialog">
            <form action="{{ route('accounts.adjust', $account) }}" method="POST" class="modal-content">
                @csrf
                <div class="modal-header"><h5 class="modal-title">Ajuste de {{ $account->name }}</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button></div>
                <div class="modal-body">
                    <p class="small text-muted">Para corregir una diferencia al contar el dinero o conciliar con el banco.</p>
                    <label class="form-label small mb-1">Tipo</label>
                    <select name="direction" class="form-select form-select-sm mb-2">
                        <option value="in">Suma al saldo</option>
                        <option value="out">Resta del saldo</option>
                    </select>
                    <label class="form-label small mb-1">Monto</label>
                    <div class="input-group mb-2"><span class="input-group-text">{{ $setting->currency_symbol }}</span><input type="number" name="amount" class="form-control text-end no-uppercase" min="0.01" step="0.01" required></div>
                    <label class="form-label small mb-1">Motivo <span class="text-danger">*</span></label>
                    <input type="text" name="reason" class="form-control form-control-sm" maxlength="200" required>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-light border" data-bs-dismiss="modal">Cancelar</button><button class="btn btn-primary">Registrar ajuste</button></div>
            </form>
        </div>
    </div>
@endif
@endsection
