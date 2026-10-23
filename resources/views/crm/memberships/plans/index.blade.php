@extends('layouts.app')

@section('title', 'Planes de membresía')

@section('page')
@php
    $user = auth()->user();
    $co = $user->getCurrentCompany();
    $can = fn ($p) => $user->is_super_admin || ($co && $user->hasPermissionInCompany($p, $co));
    $setting = \App\Models\CompanySetting::current();
    $pct = fn ($n) => rtrim(rtrim(number_format((float) $n, 2), '0'), '.');
@endphp
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div>
            <h1 class="mb-1"><i class="bi bi-award"></i> Membresías</h1>
            <p class="text-muted mb-0">Planes que el salón vende a sus clientes desde el punto de venta.</p>
        </div>
        @if(empty($noCompany) && $can('memberships.create'))
            <a href="{{ route('memberships.plans.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Nuevo plan</a>
        @endif
    </div>

    @if(!empty($noCompany))
        <div class="alert alert-info"><i class="bi bi-info-circle-fill"></i> Las membresías se configuran dentro de una empresa.</div>
    @else
        @include('crm.memberships._tabs')


        @if($plans->isEmpty())
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center py-5">
                    <i class="bi bi-award text-muted" style="font-size: 2.2rem;"></i>
                    <h6 class="mt-3">Todavía no hay planes de membresía</h6>
                    <p class="text-muted small mb-3">Crea uno, por ejemplo «Mensual Uñas: 10 % en servicios + 4 manicuras», y véndelo desde el punto de venta.</p>
                    @if($can('memberships.create'))
                        <a href="{{ route('memberships.plans.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i> Crear el primer plan</a>
                    @endif
                </div>
            </div>
        @else
            <div class="row g-3">
                @foreach($plans as $plan)
                    <div class="col-md-6 col-xl-4">
                        <div class="card border-0 shadow-sm h-100 {{ $plan->active ? '' : 'opacity-75' }}">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start gap-2">
                                    <div>
                                        <h6 class="fw-bold mb-0">{{ $plan->name }}</h6>
                                        <div class="small text-muted">{{ $plan->durationLabel() }}</div>
                                    </div>
                                    <span class="badge {{ $plan->active ? 'bg-success' : 'bg-secondary' }}">{{ $plan->active ? 'A la venta' : 'Inactivo' }}</span>
                                </div>

                                <div class="fs-4 fw-bold my-2">{{ $setting->money($plan->price) }}</div>

                                <ul class="list-unstyled small mb-2">
                                    @if((float) $plan->discount_percent > 0)
                                        <li><i class="bi bi-percent text-danger"></i> {{ $pct($plan->discount_percent) }} % en {{ mb_strtolower($plan->scopeLabel()) }}</li>
                                    @endif
                                    @foreach($plan->sessions as $s)
                                        <li><i class="bi bi-check2-circle text-success"></i> {{ $s->quantity }} × {{ $s->service?->name ?? 'servicio eliminado' }}</li>
                                    @endforeach
                                </ul>
                                @if($plan->description)<p class="small text-muted mb-2">{{ $plan->description }}</p>@endif

                                <div class="d-flex justify-content-between align-items-center mt-auto pt-2 border-top">
                                    <span class="small text-muted"><i class="bi bi-people"></i> {{ $activeCounts[$plan->id] ?? 0 }} vigente(s)</span>
                                    <div class="d-flex gap-1">
                                        @if($can('memberships.edit'))
                                            <a href="{{ route('memberships.plans.edit', $plan) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
                                        @endif
                                        @if($can('memberships.delete'))
                                            <form action="{{ route('memberships.plans.destroy', $plan) }}" method="POST" onsubmit="return confirm('¿Eliminar el plan {{ $plan->name }}? Las membresías ya vendidas siguen vigentes.')">
                                                @csrf @method('DELETE')
                                                <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                            </form>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    @endif
</div>
@endsection
