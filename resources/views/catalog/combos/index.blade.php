@extends('layouts.app')

@section('title', 'Combos')

@section('page')
@php
    $user = auth()->user();
    $co = $user->getCurrentCompany();
    $can = fn ($p) => $user->is_super_admin || ($co && $user->hasPermissionInCompany($p, $co));
    $setting = \App\Models\CompanySetting::current();
    $qty = fn ($n) => rtrim(rtrim(number_format((float) $n, 2), '0'), '.');
@endphp
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div>
            <h1 class="mb-1"><i class="bi bi-collection"></i> Combos y Paquetes</h1>
            <p class="text-muted mb-0">Combos: varios servicios y productos a un precio especial, cobrados en la misma venta.</p>
        </div>
        @if(empty($noCompany) && $can('combos.create'))
            <a href="{{ route('combos.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Nuevo combo</a>
        @endif
    </div>

    @if(!empty($noCompany))
        <div class="alert alert-info"><i class="bi bi-info-circle-fill"></i> Los combos se configuran dentro de una empresa.</div>
    @else
        @include('catalog._bundles_tabs')

        @if($combos->isEmpty())
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center py-5">
                    <i class="bi bi-collection text-muted" style="font-size: 2.2rem;"></i>
                    <h6 class="mt-3">Todavía no hay combos</h6>
                    <p class="text-muted small mb-3">Por ejemplo «Novia: peinado + maquillaje + manicura» a un precio menor que por separado.</p>
                    @if($can('combos.create'))
                        <a href="{{ route('combos.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg"></i> Crear el primer combo</a>
                    @endif
                </div>
            </div>
        @else
            <div class="row g-3">
                @foreach($combos as $combo)
                    @php
                        $list = $combo->listPrice();
                        $saving = $list > 0 ? round(100 * ($list - (float) $combo->price) / $list) : 0;
                    @endphp
                    <div class="col-md-6 col-xl-4">
                        <div class="card border-0 shadow-sm h-100 {{ $combo->active ? '' : 'opacity-75' }}">
                            <div class="card-body d-flex flex-column">
                                <div class="d-flex justify-content-between align-items-start gap-2">
                                    <h6 class="fw-bold mb-0">{{ $combo->name }}</h6>
                                    <span class="badge {{ $combo->active ? 'bg-success' : 'bg-secondary' }}">{{ $combo->active ? 'A la venta' : 'Inactivo' }}</span>
                                </div>

                                <div class="d-flex align-items-baseline gap-2 my-2">
                                    <span class="fs-4 fw-bold">{{ $setting->money($combo->price) }}</span>
                                    @if($list > (float) $combo->price)
                                        <span class="text-muted text-decoration-line-through small">{{ $setting->money($list) }}</span>
                                        <span class="badge bg-danger-subtle text-danger-emphasis">−{{ $saving }} %</span>
                                    @endif
                                </div>

                                <ul class="list-unstyled small mb-2">
                                    @foreach($combo->items as $i)
                                        <li>
                                            <i class="bi {{ $i->product_id ? 'bi-box text-warning' : 'bi-scissors text-primary' }}"></i>
                                            {{ (float) $i->quantity != 1 ? $qty($i->quantity) . ' × ' : '' }}{{ $i->name() }}
                                        </li>
                                    @endforeach
                                </ul>
                                @if($combo->description)<p class="small text-muted mb-2">{{ $combo->description }}</p>@endif

                                <div class="d-flex justify-content-between align-items-center mt-auto pt-2 border-top">
                                    <span class="small text-muted"><i class="bi bi-receipt"></i> {{ $sold[$combo->id] ?? 0 }} venta(s)</span>
                                    <div class="d-flex gap-1">
                                        @if($can('combos.edit'))
                                            <a href="{{ route('combos.edit', $combo) }}" class="btn btn-sm btn-outline-primary" title="Editar"><i class="bi bi-pencil"></i></a>
                                        @endif
                                        @if($can('combos.delete'))
                                            <form action="{{ route('combos.destroy', $combo) }}" method="POST" onsubmit="return confirm('¿Eliminar el combo {{ $combo->name }}?')">
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
