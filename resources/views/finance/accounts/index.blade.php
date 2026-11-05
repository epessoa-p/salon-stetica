@extends('layouts.app')

@section('title', 'Cuentas')

@section('page')
@php
    $authUser = auth()->user();
    $authCompany = $authUser->getCurrentCompany();
@endphp
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h1 class="mb-1"><i class="bi bi-bank"></i> Cuentas</h1>
            <p class="text-muted mb-0">Caja chica, bancos y otras cuentas con su saldo y movimientos.</p>
        </div>
        @if($authUser->hasPermissionInCompany('accounts.create', $authCompany) && !$noCompany)
            <a href="{{ route('accounts.create') }}" class="btn btn-primary"><i class="bi bi-plus-circle"></i> Nueva cuenta</a>
        @endif
    </div>

    @if($errors->any())<div class="alert alert-danger"><ul class="mb-0 ps-3">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

    @if($noCompany)
        <div class="alert alert-info d-flex align-items-center gap-2"><i class="bi bi-info-circle-fill"></i> Este módulo se gestiona dentro de una empresa.</div>
    @else
        @if($accounts->isEmpty())
            <div class="card border-0 shadow-sm"><div class="card-body text-center text-muted py-5">
                <i class="bi bi-piggy-bank" style="font-size: 2rem;"></i>
                <p class="mt-2 mb-0">Aún no hay cuentas. Crea una «Caja chica» con su aporte inicial para empezar a registrar gastos desde ella.</p>
            </div></div>
        @else
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-body d-flex justify-content-between align-items-center">
                    <span class="text-muted">Disponible en cuentas activas</span>
                    <span class="fs-4 fw-bold">{{ $setting->money($total) }}</span>
                </div>
            </div>

            <div class="row g-3">
                @foreach($accounts as $a)
                    @php $last = $lastMoves[$a->id] ?? null; @endphp
                    <div class="col-md-6 col-xl-4">
                        <a href="{{ route('accounts.show', $a) }}" class="card border-0 shadow-sm h-100 text-decoration-none text-body {{ $a->active ? '' : 'opacity-50' }}">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <div class="fw-bold"><i class="bi {{ $a->icon() }} text-primary"></i> {{ $a->name }}</div>
                                        <div class="small text-muted">{{ $a->typeLabel() }}{{ $a->bank_name ? ' · ' . $a->bank_name : '' }}</div>
                                    </div>
                                    @unless($a->active)<span class="badge bg-secondary">Inactiva</span>@endunless
                                </div>
                                <div class="fs-4 fw-bold mt-2">{{ $setting->money((float) $a->saldo) }}</div>
                                <div class="small text-muted">
                                    @if($last)
                                        Último: {{ $last->concept }} · {{ $last->movement_date->format('d/m/Y') }}
                                    @else
                                        Sin movimientos
                                    @endif
                                </div>
                            </div>
                        </a>
                    </div>
                @endforeach
            </div>
        @endif
    @endif
</div>
@endsection
