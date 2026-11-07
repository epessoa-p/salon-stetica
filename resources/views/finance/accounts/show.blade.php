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

    <div class="row g-3 mb-3">
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small text-uppercase fw-bold" style="letter-spacing: 1px;">Saldo actual</div>
                    <div class="fs-2 fw-bold">{{ $setting->money($balance) }}</div>
                    @if($canExpense && $account->active)
                        <a href="{{ route('expenses.create', ['account' => $account->id]) }}" class="btn btn-sm btn-outline-danger mt-2"><i class="bi bi-arrow-up-circle"></i> Registrar gasto</a>
                    @endif
                </div>
            </div>
        </div>
        @if($canMove && $account->active)
            <div class="col-lg-8">
                <div class="card border-0 shadow-sm">
                    <div class="card-body">
                        <ul class="nav nav-pills mb-3 gap-1" role="tablist">
                            <li class="nav-item" role="presentation"><button class="nav-link active" data-bs-toggle="pill" data-bs-target="#pane-move" type="button" role="tab">Ingreso / egreso</button></li>
                            <li class="nav-item" role="presentation"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#pane-transfer" type="button" role="tab">Transferir</button></li>
                        </ul>
                        <div class="tab-content">
                            <div class="tab-pane fade show active" id="pane-move" role="tabpanel">
                                <form action="{{ route('accounts.movement', $account) }}" method="POST">
                                    @csrf
                                    <div class="row g-2">
                                        <div class="col-md-3">
                                            <label class="form-label small mb-1">Tipo</label>
                                            <select name="direction" id="mv-direction" class="form-select form-select-sm">
                                                <option value="in">Ingreso</option>
                                                <option value="out">Egreso</option>
                                            </select>
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label small mb-1">Importe</label>
                                            <input type="number" name="amount" class="form-control form-control-sm text-end no-uppercase" min="0.01" step="0.01" required>
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label small mb-1">Fecha</label>
                                            <input type="date" name="movement_date" class="form-control form-control-sm no-uppercase" value="{{ now()->toDateString() }}" max="{{ now()->toDateString() }}" required>
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label small mb-1">Referencia</label>
                                            <input type="text" name="reference" class="form-control form-control-sm no-uppercase" maxlength="255">
                                        </div>
                                        <div class="col-12">
                                            <label class="form-label small mb-1">Concepto</label>
                                            <input type="text" name="concept" class="form-control form-control-sm" maxlength="255" required>
                                        </div>
                                        <div class="col-12" id="mv-source">
                                            @include('finance.partials.payment_source', ['sources' => ['sessions' => $sources['sessions'], 'accounts' => $others, 'canUseAccounts' => $sources['canUseAccounts']], 'label' => 'Viene de', 'noneLabel' => 'Externo (dueño o socio)', 'preferNone' => true])
                                        </div>
                                    </div>
                                    <button class="btn btn-primary btn-sm mt-2">Registrar movimiento</button>
                                </form>
                            </div>
                            <div class="tab-pane fade" id="pane-transfer" role="tabpanel">
                                @if($others->isEmpty() && $sources['sessions']->isEmpty())
                                    <p class="small text-muted mb-0">No hay otra cuenta ni caja abierta a la que transferir.</p>
                                @else
                                    <form action="{{ route('accounts.transfer', $account) }}" method="POST">
                                        @csrf
                                        <div class="row g-2">
                                            <div class="col-md-5">
                                                <label class="form-label small mb-1">Hacia</label>
                                                <select name="destination" class="form-select form-select-sm" required>
                                                    <option value="">Seleccionar...</option>
                                                    @if($others->isNotEmpty())
                                                        <optgroup label="Cuentas">@foreach($others as $o)<option value="cuenta:{{ $o->id }}">{{ $o->name }}</option>@endforeach</optgroup>
                                                    @endif
                                                    @if($sources['sessions']->isNotEmpty())
                                                        <optgroup label="Cajas abiertas">@foreach($sources['sessions'] as $s)<option value="caja:{{ $s->id }}">{{ $s->register?->name ?? 'Caja' }}</option>@endforeach</optgroup>
                                                    @endif
                                                </select>
                                            </div>
                                            <div class="col-md-3">
                                                <label class="form-label small mb-1">Importe</label>
                                                <input type="number" name="amount" class="form-control form-control-sm text-end no-uppercase" min="0.01" step="0.01" max="{{ $balance }}" required>
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label small mb-1">Fecha</label>
                                                <input type="date" name="movement_date" class="form-control form-control-sm no-uppercase" value="{{ now()->toDateString() }}" max="{{ now()->toDateString() }}" required>
                                            </div>
                                            <div class="col-12">
                                                <label class="form-label small mb-1">Concepto (opcional)</label>
                                                <input type="text" name="concept" class="form-control form-control-sm" maxlength="255">
                                            </div>
                                        </div>
                                        <button class="btn btn-primary btn-sm mt-2">Transferir</button>
                                    </form>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @endif
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
                            <th>Concepto</th>
                            <th>Origen</th>
                            <th class="text-end">Ingreso</th>
                            <th class="text-end">Egreso</th>
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
                                    <small class="text-muted">{{ $m->reference ? 'Ref: ' . $m->reference . ' · ' : '' }}{{ $m->creator?->name }}</small>
                                    @if($m->isVoided())<span class="badge bg-secondary">Anulado {{ $m->voided_at->format('d/m/Y') }}</span>@endif
                                </td>
                                <td>{{ $m->typeLabel() }}</td>
                                <td class="text-end text-success fw-semibold">{{ $m->isIn() ? number_format($m->amount, 2) : '' }}</td>
                                <td class="text-end text-danger fw-semibold">{{ $m->isIn() ? '' : number_format($m->amount, 2) }}</td>
                                <td class="text-end">
                                    @unless($m->isVoided())
                                        {{ number_format($running, 2) }}
                                        @php $running -= $m->signedAmount(); @endphp
                                    @endunless
                                </td>
                                <td class="text-end pe-3">
                                    @if($canMove && $m->canVoidHere())
                                        <form action="{{ route('accounts.movements.void', [$account, $m]) }}" method="POST" class="d-inline" onsubmit="return confirm('¿Anular este movimiento?')">
                                            @csrf
                                            <button class="btn btn-sm btn-outline-danger" title="Anular"><i class="bi bi-x-lg"></i></button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="text-center py-5 text-muted">No hay movimientos.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer bg-white">{{ $movements->links() }}</div>
    </div>
</div>
@if($canMove && $account->active)
<script>
    // «Viene de» solo aplica a los ingresos.
    (function () {
        const direction = document.getElementById('mv-direction');
        const box = document.getElementById('mv-source');
        const sync = () => {
            const isIn = direction.value === 'in';
            box.classList.toggle('d-none', !isIn);
            if (!isIn) box.querySelector('[value=ninguno]').click();
        };
        direction.addEventListener('change', sync);
        sync();
    })();
</script>
@endif
@endsection
