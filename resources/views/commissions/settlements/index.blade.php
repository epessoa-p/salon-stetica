@extends('layouts.app')

@section('title', 'Liquidaciones')

@section('page')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h1 class="mb-1"><i class="bi bi-journal-check"></i> Liquidaciones</h1>
            <p class="text-muted mb-0">Historial de pagos de comisiones a los profesionales.</p>
        </div>
        <a href="{{ route('commissions.index') }}" class="btn btn-outline-secondary"><i class="bi bi-percent"></i> Comisiones</a>
    </div>

    @if($noCompany)
        <div class="alert alert-info d-flex align-items-center gap-2"><i class="bi bi-info-circle-fill"></i> Las liquidaciones se consultan dentro de una empresa.</div>
    @else
        <form method="GET" class="card border-0 shadow-sm mb-3">
            <div class="card-body">
                <div class="row g-2 align-items-end">
                    <div class="col-md-6">
                        <label class="form-label">Profesional</label>
                        <select name="personal_id" class="form-select" onchange="this.form.submit()">
                            <option value="">Todos</option>
                            @foreach($specialists as $sp)
                                <option value="{{ $sp->id }}" @selected($personalId === $sp->id)>{{ $sp->full_name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>
        </form>

        <div class="card border-0 shadow-sm">
            <div class="card-body p-0">
                @if($settlements->isEmpty())
                    <p class="text-muted text-center py-4 mb-0">Todavía no hay liquidaciones registradas.</p>
                @else
                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-3">Número</th>
                                    <th>Profesional</th>
                                    <th>Periodo</th>
                                    <th class="text-center">Comisiones</th>
                                    <th class="text-end">Propinas</th>
                                    <th class="text-end">Total</th>
                                    <th>Pagado</th>
                                    <th class="pe-3"></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($settlements as $s)
                                    <tr>
                                        <td class="ps-3"><code>{{ $s->number }}</code></td>
                                        <td class="fw-semibold">{{ $s->specialist?->full_name }}</td>
                                        <td class="small text-nowrap">{{ $s->period_start->format('d/m/Y') }} – {{ $s->period_end->format('d/m/Y') }}</td>
                                        <td class="text-center">{{ $s->commissions_count }} · {{ number_format((float) $s->commissions_total, 2) }}</td>
                                        <td class="text-end">{{ number_format((float) $s->tips_total, 2) }}</td>
                                        <td class="text-end fw-bold">{{ number_format((float) $s->total, 2) }}</td>
                                        <td class="small text-muted text-nowrap">{{ $s->paid_at->format('d/m/Y H:i') }}<br>{{ $s->payer?->name }}</td>
                                        <td class="pe-3 text-end"><a href="{{ route('commission-settlements.show', $s) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-eye"></i></a></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
        <div class="mt-3">{{ $settlements->links() }}</div>
    @endif
</div>
@endsection
