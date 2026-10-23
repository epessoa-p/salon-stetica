@extends('layouts.app')

@section('title', 'Paquetes')

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
            <h1 class="mb-1"><i class="bi bi-collection"></i> Combos y Paquetes</h1>
            <p class="text-muted mb-0">Paquetes: sesiones prepagadas que el cliente usa en sus próximas visitas.</p>
        </div>
        @if(empty($noCompany) && $can('packages.create'))
            <a href="{{ route('packages.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Nuevo paquete</a>
        @endif
    </div>

    @if(!empty($noCompany))
        <div class="alert alert-info"><i class="bi bi-info-circle-fill"></i> Los paquetes se configuran dentro de una empresa.</div>
    @else
        @include('catalog._bundles_tabs')

        @if($packages->isEmpty())
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center py-5">
                    <i class="bi bi-ticket-perforated text-muted" style="font-size: 2.2rem;"></i>
                    <h6 class="mt-3">Todavía no hay paquetes</h6>
                    <p class="text-muted small mb-3">Por ejemplo «10 depilaciones láser» pagadas por adelantado y usadas en cada visita.</p>
                    @if($can('packages.create'))
                        <a href="{{ route('packages.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i> Crear el primer paquete</a>
                    @endif
                </div>
            </div>
        @else
            <div class="row g-3">
                @foreach($packages as $package)
                    @php
                        $list = $package->listPrice();
                        $saving = $list > 0 ? round(100 * ($list - (float) $package->price) / $list) : 0;
                    @endphp
                    <div class="col-md-6 col-xl-4">
                        <div class="card border-0 shadow-sm h-100 {{ $package->active ? '' : 'opacity-75' }}">
                            <div class="card-body d-flex flex-column">
                                <div class="d-flex justify-content-between align-items-start gap-2">
                                    <div>
                                        <h6 class="fw-bold mb-0">{{ $package->name }}</h6>
                                        <div class="small text-muted">{{ $package->validityLabel() }}</div>
                                    </div>
                                    <span class="badge {{ $package->active ? 'bg-success' : 'bg-secondary' }}">{{ $package->active ? 'A la venta' : 'Inactivo' }}</span>
                                </div>

                                <div class="d-flex align-items-baseline gap-2 my-2">
                                    <span class="fs-4 fw-bold">{{ $setting->money($package->price) }}</span>
                                    @if($list > (float) $package->price)
                                        <span class="text-muted text-decoration-line-through small">{{ $setting->money($list) }}</span>
                                        <span class="badge bg-danger-subtle text-danger-emphasis">−{{ $saving }} %</span>
                                    @endif
                                </div>

                                <ul class="list-unstyled small mb-2">
                                    @foreach($package->items as $i)
                                        <li><i class="bi bi-check2-circle text-success"></i> {{ $i->quantity }} × {{ $i->service?->name ?? 'servicio eliminado' }}</li>
                                    @endforeach
                                </ul>
                                @if($package->description)<p class="small text-muted mb-2">{{ $package->description }}</p>@endif

                                <div class="d-flex justify-content-between align-items-center mt-auto pt-2 border-top">
                                    <a href="{{ route('packages.sold') }}" class="small text-muted text-decoration-none"><i class="bi bi-people"></i> {{ $activeCounts[$package->id] ?? 0 }} activo(s)</a>
                                    <div class="d-flex gap-1">
                                        @if($can('packages.edit'))
                                            <a href="{{ route('packages.edit', $package) }}" class="btn btn-sm btn-outline-primary" title="Editar"><i class="bi bi-pencil"></i></a>
                                        @endif
                                        @if($can('packages.delete'))
                                            <form action="{{ route('packages.destroy', $package) }}" method="POST" onsubmit="return confirm('¿Eliminar el paquete {{ $package->name }}? Los ya vendidos se siguen pudiendo usar.')">
                                                @csrf @method('DELETE')
                                                <button class="btn btn-sm btn-outline-danger" title="Eliminar"><i class="bi bi-trash"></i></button>
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
