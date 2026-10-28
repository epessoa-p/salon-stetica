@extends('layouts.app')

@section('title', 'Promociones')

@section('page')
@php
    $user = auth()->user();
    $co = $user->getCurrentCompany();
    $can = fn ($p) => $user->is_super_admin || ($co && $user->hasPermissionInCompany($p, $co));
    $setting = \App\Models\CompanySetting::current();
@endphp
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div>
            <h1 class="mb-1"><i class="bi bi-percent"></i> Promociones</h1>
            <p class="text-muted mb-0">Descuentos automáticos por día y servicio. El punto de venta los aplica solos; si el cliente tiene membresía, recibe el mayor de los dos.</p>
        </div>
        @if(empty($noCompany) && $can('promotions.create'))
            <a href="{{ route('marketing.promotions.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Nueva promoción</a>
        @endif
    </div>

    @if(!empty($noCompany))
        <div class="alert alert-info"><i class="bi bi-info-circle-fill"></i> Las promociones se configuran dentro de una empresa.</div>
    @else
        <div class="row g-3">
            @forelse($promotions as $p)
                @php $st = $stats->get($p->id); $today = $p->isValidOn(today()); @endphp
                <div class="col-md-6 col-xl-4">
                    <div class="card border-0 shadow-sm h-100 {{ $p->active ? '' : 'opacity-50' }}">
                        <div class="card-body d-flex flex-column">
                            <div class="d-flex justify-content-between align-items-start gap-2">
                                <h6 class="fw-bold mb-0">{{ $p->name }}</h6>
                                <span class="badge {{ $today ? 'bg-success' : 'bg-secondary' }}">{{ $today ? 'Activa hoy' : ($p->active ? 'No aplica hoy' : 'Inactiva') }}</span>
                            </div>
                            <div class="fs-4 fw-bold my-2">−{{ $p->valueLabel($setting->currency_symbol) }}</div>
                            <div class="small"><i class="bi bi-bullseye"></i> {{ $p->targetLabel() }}</div>
                            <div class="small text-muted"><i class="bi bi-calendar-week"></i> {{ $p->scheduleLabel() }}</div>
                            <div class="d-flex justify-content-between align-items-center mt-auto pt-2 border-top">
                                <span class="small text-muted">{{ (int) ($st->uses ?? 0) }} línea(s) · −{{ number_format((float) ($st->discount ?? 0), 2) }}</span>
                                <div class="d-flex gap-1">
                                    @if($can('promotions.edit'))<a href="{{ route('marketing.promotions.edit', $p) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>@endif
                                    @if($can('promotions.delete'))
                                        <form action="{{ route('marketing.promotions.destroy', $p) }}" method="POST" onsubmit="return confirm('¿Eliminar la promoción?')">
                                            @csrf @method('DELETE')
                                            <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                        </form>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @empty
                <div class="col-12"><div class="card border-0 shadow-sm"><div class="card-body text-center text-muted py-5">
                    Sin promociones. Por ejemplo: «Martes de manicura: 20 %».
                </div></div></div>
            @endforelse
        </div>
    @endif
</div>
@endsection
