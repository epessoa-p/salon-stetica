@extends('layouts.app')

@section('title', 'Comisiones')

@section('page')
@php $setting = \App\Models\CompanySetting::current(); @endphp
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h1 class="mb-1"><i class="bi bi-percent"></i> Comisiones</h1>
            <p class="text-muted mb-0">Lo que ganó cada profesional, lo que se le debe y desde dónde se le paga.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('commission-settlements.index') }}" class="btn btn-outline-secondary"><i class="bi bi-journal-check"></i> Liquidaciones</a>
            @if(auth()->user()->is_super_admin || auth()->user()->hasPermissionInCompany('commission-rules.view', auth()->user()->getCurrentCompany()))
                <a href="{{ route('commission-rules.index') }}" class="btn btn-outline-secondary"><i class="bi bi-sliders"></i> Reglas</a>
            @endif
        </div>
    </div>

    @if($noCompany)
        <div class="alert alert-info d-flex align-items-center gap-2"><i class="bi bi-info-circle-fill"></i> Las comisiones se consultan dentro de una empresa.</div>
    @else
        @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show"><i class="bi bi-check-circle"></i> {{ session('success') }}<button class="btn-close" data-bs-dismiss="alert"></button></div>
        @endif
        @if($errors->any())
            <div class="alert alert-danger"><ul class="mb-0 ps-3">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
        @endif

        {{-- Filtros --}}
        <form method="GET" class="card border-0 shadow-sm mb-3">
            <div class="card-body">
                <div class="row g-2 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label">Desde</label>
                        <input type="date" name="from" class="form-control" value="{{ $from->toDateString() }}">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Hasta</label>
                        <input type="date" name="to" class="form-control" value="{{ $to->toDateString() }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Profesional</label>
                        <select name="personal_id" class="form-select">
                            <option value="">Todos (resumen)</option>
                            @foreach($specialists as $sp)
                                <option value="{{ $sp->id }}" @selected($personal && $personal->id === $sp->id)>{{ $sp->full_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2 d-grid">
                        <button class="btn btn-primary"><i class="bi bi-funnel"></i> Ver</button>
                    </div>
                </div>
            </div>
        </form>

        {{-- Resumen por profesional --}}
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white border-0 pt-3 d-flex justify-content-between align-items-center">
                <h6 class="fw-bold mb-0">Resumen del periodo</h6>
                <span class="text-muted small">{{ $from->format('d/m/Y') }} – {{ $to->format('d/m/Y') }}</span>
            </div>
            <div class="card-body p-0">
                @if($summary->isEmpty())
                    <p class="text-muted text-center py-4 mb-0">Sin comisiones ni propinas en este periodo.</p>
                @else
                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-3">Profesional</th>
                                    <th class="text-center">Comisiones</th>
                                    <th class="text-end">Ganado</th>
                                    <th class="text-end">Pendiente</th>
                                    <th class="text-end">Propinas</th>
                                    <th class="text-end pe-3"></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($summary as $row)
                                    <tr class="{{ $personal && $personal->id === $row->personal_id ? 'table-active' : '' }}">
                                        <td class="ps-3 fw-semibold">{{ $row->personal?->full_name ?? 'Profesional eliminado' }}</td>
                                        <td class="text-center">{{ $row->count }}</td>
                                        <td class="text-end">{{ number_format($row->earned, 2) }}</td>
                                        <td class="text-end {{ $row->pending > 0 ? 'fw-semibold text-warning-emphasis' : 'text-muted' }}">{{ number_format($row->pending, 2) }}</td>
                                        <td class="text-end">
                                            {{ number_format($row->tips, 2) }}
                                            @if($row->tips_pending > 0)<small class="text-muted d-block">pend. {{ number_format($row->tips_pending, 2) }}</small>@endif
                                        </td>
                                        <td class="text-end pe-3">
                                            <a href="{{ route('commissions.index', ['from' => $from->toDateString(), 'to' => $to->toDateString(), 'personal_id' => $row->personal_id]) }}" class="btn btn-sm btn-outline-primary">Detalle</a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot class="table-light fw-semibold">
                                <tr>
                                    <td class="ps-3">Total</td>
                                    <td class="text-center">{{ $totals['count'] }}</td>
                                    <td class="text-end">{{ number_format($totals['earned'], 2) }}</td>
                                    <td class="text-end">{{ number_format($totals['pending'], 2) }}</td>
                                    <td class="text-end">{{ number_format($totals['tips'], 2) }}</td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                @endif
            </div>
        </div>

        @if($personal)
            @include('commissions.commissions._detail')
        @endif
    @endif
</div>
@endsection
