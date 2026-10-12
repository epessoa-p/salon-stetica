@extends('layouts.app')

@section('title', 'Reglas de comisión')

@section('page')
@php
    $user = auth()->user();
    $co = $user->getCurrentCompany();
    $can = fn ($p) => $user->is_super_admin || ($co && $user->hasPermissionInCompany($p, $co));
@endphp
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h1 class="mb-1"><i class="bi bi-sliders"></i> Reglas de comisión</h1>
            <p class="text-muted mb-0">Cuánto se paga, a quién y por qué. Gana siempre la regla más específica.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('commissions.index') }}" class="btn btn-outline-secondary"><i class="bi bi-percent"></i> Comisiones</a>
            @if(!$noCompany && $can('commission-rules.create'))
                <a href="{{ route('commission-rules.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Nueva regla</a>
            @endif
        </div>
    </div>

    @if($noCompany)
        <div class="alert alert-info d-flex align-items-center gap-2"><i class="bi bi-info-circle-fill"></i> Las reglas se configuran dentro de una empresa.</div>
    @else
        @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show"><i class="bi bi-check-circle"></i> {{ session('success') }}<button class="btn-close" data-bs-dismiss="alert"></button></div>
        @endif

        @if(!$hasActive)
            <div class="alert alert-warning d-flex align-items-center gap-2">
                <i class="bi bi-exclamation-triangle-fill"></i>
                <div><strong>No hay ninguna regla activa:</strong> en este momento las ventas no están generando comisiones.</div>
            </div>
        @endif

        <div class="row g-3">
            <div class="col-xl-8">
                <div class="card border-0 shadow-sm">
                    <div class="card-body p-0">
                        @if($rules->isEmpty())
                            <p class="text-muted text-center py-4 mb-0">Todavía no hay reglas. Empieza por una general, por ejemplo «Todo el equipo · Todos los servicios · 40 %».</p>
                        @else
                            <div class="table-responsive">
                                <table class="table align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th class="ps-3 text-center" title="Prioridad: mayor gana">Prior.</th>
                                            <th>A quién</th>
                                            <th>Sobre qué</th>
                                            <th class="text-end">Paga</th>
                                            <th>Vigencia</th>
                                            <th class="text-center">Estado</th>
                                            <th class="pe-3"></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($rules as $r)
                                            <tr class="{{ $r->active ? '' : 'text-muted' }}">
                                                <td class="ps-3 text-center"><span class="badge bg-secondary-subtle text-secondary-emphasis">{{ $r->priority() }}</span></td>
                                                <td class="{{ $r->personal_id ? 'fw-semibold' : '' }}">{{ $r->whoLabel() }}</td>
                                                <td>
                                                    {{ $r->targetLabel() }}
                                                    @if($r->notes)<i class="bi bi-chat-left-text text-muted ms-1" title="{{ $r->notes }}"></i>@endif
                                                </td>
                                                <td class="text-end fw-semibold">{{ $r->rateLabel() }}</td>
                                                <td class="small">{{ $r->validityLabel() }}</td>
                                                <td class="text-center">
                                                    <span class="badge {{ $r->active ? 'bg-success' : 'bg-secondary' }}">{{ $r->active ? 'Activa' : 'Inactiva' }}</span>
                                                </td>
                                                <td class="pe-3 text-end text-nowrap">
                                                    @if($can('commission-rules.edit'))
                                                        <a href="{{ route('commission-rules.edit', $r) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
                                                    @endif
                                                    @if($can('commission-rules.delete'))
                                                        <form action="{{ route('commission-rules.destroy', $r) }}" method="POST" class="d-inline"
                                                              onsubmit="return confirm('¿Eliminar esta regla? Las comisiones ya devengadas no cambian.')">
                                                            @csrf @method('DELETE')
                                                            <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                                        </form>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Cómo se calcula --}}
            <div class="col-xl-4">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-0 pt-3"><h6 class="fw-bold mb-0"><i class="bi bi-calculator text-primary"></i> Cómo se calcula</h6></div>
                    <div class="card-body pt-1 small">
                        <p class="mb-2">Para cada línea cobrada se aplica <strong>una sola regla</strong>: la activa y vigente de mayor prioridad.</p>
                        <table class="table table-sm mb-3">
                            <tbody>
                                <tr><td class="text-center">6</td><td>Profesional · un servicio</td></tr>
                                <tr><td class="text-center">5</td><td>Profesional · una categoría</td></tr>
                                <tr><td class="text-center">4</td><td>Profesional · todos los servicios/productos</td></tr>
                                <tr><td class="text-center">3</td><td>Equipo · un servicio</td></tr>
                                <tr><td class="text-center">2</td><td>Equipo · una categoría</td></tr>
                                <tr><td class="text-center">1</td><td>Equipo · todos los servicios/productos</td></tr>
                            </tbody>
                        </table>
                        <ul class="ps-3 mb-0">
                            <li class="mb-1"><strong>Base:</strong> el total de la línea (cantidad × precio − descuento de esa línea). El descuento global de la venta lo asume el salón.</li>
                            <li class="mb-1"><strong>Monto fijo:</strong> se paga por unidad. Tres manicuras con una regla de 20 pagan 60.</li>
                            <li class="mb-1"><strong>Propina:</strong> no es comisión; va íntegra al profesional principal y se paga aparte.</li>
                            <li class="mb-1"><strong>Sin regla no hay comisión:</strong> no existe un porcentaje por defecto.</li>
                            <li class="mb-1"><strong>Lo devengado no cambia:</strong> editar o borrar una regla solo afecta a lo que se cobre después.</li>
                            <li><strong>Venta anulada:</strong> se borran sus comisiones pendientes; si ya se pagaron, se genera un reverso negativo que se descuenta en la siguiente liquidación.</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
@endsection
