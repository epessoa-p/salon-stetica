@extends('layouts.app')

@section('title', 'Gastos')

@section('page')
@php
    $authUser = auth()->user();
    $authCompany = $authUser->getCurrentCompany();
    $canCreate = $authUser->hasPermissionInCompany('expenses.create', $authCompany);
    $canNewService = $authUser->hasPermissionInCompany('expense_categories.create', $authCompany);
    $group = $group ?? null;
    $createLabel = ['proveedor' => 'Pago a proveedor', 'personal' => 'Pago a personal', 'basicos' => 'Pago de servicio'][$group] ?? 'Nuevo gasto';
@endphp
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div>
            <h1 class="mb-1"><i class="bi bi-arrow-up-circle"></i> Gastos</h1>
            <p class="text-muted mb-0">Salen de una cuenta o de la caja abierta. Se anulan, no se borran.</p>
        </div>
        @if($canCreate && !$noCompany)
            <a href="{{ route('expenses.create', array_filter(['group' => $group])) }}" class="btn btn-primary"><i class="bi bi-plus-circle"></i> {{ $createLabel }}</a>
        @endif
    </div>

    @if($errors->any())<div class="alert alert-danger"><ul class="mb-0 ps-3">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

    @if($noCompany)
        <div class="alert alert-info d-flex align-items-center gap-2"><i class="bi bi-info-circle-fill"></i> Este módulo se gestiona dentro de una empresa.</div>
    @else
        <ul class="nav nav-pills mb-3 gap-1 flex-wrap">
            <li class="nav-item"><a class="nav-link {{ $group === 'general' ? 'active' : '' }}" href="{{ route('expenses.index', ['group' => 'general']) }}">Generales</a></li>
            <li class="nav-item"><a class="nav-link {{ $group === 'proveedor' ? 'active' : '' }}" href="{{ route('expenses.index', ['group' => 'proveedor']) }}">Proveedores</a></li>
            <li class="nav-item"><a class="nav-link {{ $group === 'personal' ? 'active' : '' }}" href="{{ route('expenses.index', ['group' => 'personal']) }}">Personal</a></li>
            <li class="nav-item"><a class="nav-link {{ $group === 'basicos' ? 'active' : '' }}" href="{{ route('expenses.index', ['group' => 'basicos']) }}">Servicios básicos</a></li>
            <li class="nav-item"><a class="nav-link {{ $group === null ? 'active' : '' }}" href="{{ route('expenses.index') }}">Todos</a></li>
        </ul>

        @if($owed !== null)
            @include('finance.partials.commissions_owed', ['owed' => $owed, 'setting' => $setting])
        @endif

        @if($basics)
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <a class="btn btn-sm btn-light border" title="Mes anterior" href="{{ route('expenses.index', ['group' => 'basicos', 'month' => $basics['previous']]) }}"><i class="bi bi-chevron-left"></i></a>
                        <div class="text-center">
                            <div class="fw-bold">{{ $basics['label'] }}</div>
                            <div class="small text-muted">{{ $basics['paid_count'] }} {{ $basics['paid_count'] === 1 ? 'pagado' : 'pagados' }} · {{ $basics['pending_count'] }} {{ $basics['pending_count'] === 1 ? 'pendiente' : 'pendientes' }} · {{ $setting->money($basics['total_paid']) }}</div>
                        </div>
                        @if($basics['next'])
                            <a class="btn btn-sm btn-light border" title="Mes siguiente" href="{{ route('expenses.index', ['group' => 'basicos', 'month' => $basics['next']]) }}"><i class="bi bi-chevron-right"></i></a>
                        @else
                            <span class="btn btn-sm btn-light border disabled"><i class="bi bi-chevron-right"></i></span>
                        @endif
                    </div>
                    @forelse($basics['services'] as $s)
                        <div class="d-flex justify-content-between align-items-center py-2 border-top">
                            <div>
                                <i class="bi {{ $s['status'] === 'pagado' ? 'bi-check-circle-fill text-success' : 'bi-clock text-warning' }} me-1"></i>
                                <span class="fw-semibold">{{ $s['name'] }}</span>
                                @if($s['frequency'] === 'anual')<span class="badge bg-light text-dark border">Anual</span>@endif
                                <div class="small text-muted ms-4">
                                    @if($s['status'] === 'pagado')
                                        Pagado {{ $setting->money($s['paid']) }}{{ $s['next_due_on'] ? ' · vuelve el ' . \Illuminate\Support\Carbon::parse($s['next_due_on'])->format('d/m/Y') : '' }}
                                    @else
                                        Pendiente{{ $s['last_amount'] ? ' · último ' . $setting->money($s['last_amount']) : '' }}
                                    @endif
                                </div>
                            </div>
                            @if($s['status'] !== 'pagado' && $canCreate)
                                <a class="btn btn-sm btn-outline-primary" href="{{ route('expenses.create', ['group' => 'basicos', 'category' => $s['id'], 'amount' => $s['last_amount']]) }}">Registrar</a>
                            @endif
                        </div>
                    @empty
                        <p class="small text-muted mb-0 text-center">Todavía no hay servicios básicos: agrega la luz, el agua, el internet…</p>
                    @endforelse
                    @if($canNewService)
                        <a href="{{ route('expense-categories.index', ['new' => 'basicos']) }}" class="btn btn-sm btn-link px-0 mt-2"><i class="bi bi-plus"></i> Nuevo servicio</a>
                    @endif
                </div>
            </div>
        @endif

        <form method="GET" class="card border-0 shadow-sm mb-3">
            <div class="card-body py-3">
                @if($group)<input type="hidden" name="group" value="{{ $group }}">@endif
                <div class="row g-2 align-items-end">
                    <div class="col-md-2">
                        <label class="form-label small mb-1">Desde</label>
                        <input type="date" name="from" value="{{ $filters['from'] }}" class="form-control no-uppercase">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small mb-1">Hasta</label>
                        <input type="date" name="to" value="{{ $filters['to'] }}" class="form-control no-uppercase">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small mb-1">Categoría</label>
                        <select name="category" class="form-select">
                            <option value="">Todas</option>
                            @foreach($categories as $c)
                                <option value="{{ $c->id }}" @selected((int) $filters['category'] === $c->id)>{{ $c->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small mb-1">Origen</label>
                        <select name="source" class="form-select">
                            <option value="">Todos</option>
                            <option value="cuenta" @selected($filters['source'] === 'cuenta')>Cuentas</option>
                            <option value="caja" @selected($filters['source'] === 'caja')>Caja</option>
                            <option value="ninguno" @selected($filters['source'] === 'ninguno')>Sin origen</option>
                        </select>
                    </div>
                    <div class="col-md-3 d-flex gap-2">
                        <button class="btn btn-outline-primary"><i class="bi bi-funnel"></i> Filtrar</button>
                        <a href="{{ route('expenses.index', array_filter(['group' => $group])) }}" class="btn btn-light border">Limpiar</a>
                    </div>
                </div>
            </div>
        </form>

        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body d-flex justify-content-between align-items-center">
                <span class="text-muted">Total gastado</span>
                <span class="fs-4 fw-bold text-danger">{{ $setting->money($total) }}</span>
            </div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-3">Fecha</th>
                                <th>Gasto</th>
                                <th>Categoría</th>
                                <th>A quién</th>
                                <th>Origen</th>
                                <th class="text-end pe-3">Importe</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($expenses as $m)
                                <tr class="{{ $m->isCancelled() ? 'opacity-50' : '' }}">
                                    <td class="ps-3 text-nowrap">{{ optional($m->movement_date)->format('d/m/Y') }}</td>
                                    <td>
                                        <a href="{{ route('expenses.show', $m) }}" class="fw-semibold text-decoration-none {{ $m->isCancelled() ? 'text-decoration-line-through' : '' }}">{{ $m->concept }}</a>
                                        <div class="small text-muted">{{ $m->number }}{{ $m->reference && $m->reference !== $m->number ? ' · Ref: ' . $m->reference : '' }}</div>
                                        @if($m->isCancelled())<span class="badge bg-secondary">Anulado</span>@endif
                                    </td>
                                    <td>{{ $m->expenseCategory?->name ?? ($m->category ?: '-') }}</td>
                                    <td>{{ $m->supplierRef?->name ?? ($m->personal?->full_name ?? '-') }}</td>
                                    <td>
                                        @if($m->account)
                                            <span class="badge bg-primary-subtle text-primary-emphasis"><i class="bi bi-wallet2"></i> {{ $m->account->name }}</span>
                                        @elseif($m->session)
                                            <span class="badge bg-success-subtle text-success-emphasis"><i class="bi bi-safe"></i> Caja · {{ $m->session->register?->name ?? '' }}</span>
                                        @else
                                            <span class="text-muted small">Sin origen</span>
                                        @endif
                                    </td>
                                    <td class="text-end pe-3 fw-semibold">{{ number_format($m->amount, 2) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="text-center py-5 text-muted">No hay gastos en el periodo seleccionado.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer bg-white">{{ $expenses->links() }}</div>
        </div>
    @endif
</div>
@endsection
