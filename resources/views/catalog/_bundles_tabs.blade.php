{{-- Pestañas comunes de Combos y Paquetes --}}
@php
    $tabUser = auth()->user();
    $tabCo = $tabUser->getCurrentCompany();
    $tabCan = fn ($p) => $tabUser->is_super_admin || ($tabCo && $tabUser->hasPermissionInCompany($p, $tabCo));
@endphp
<ul class="nav nav-tabs mb-3">
    @if($tabCan('combos.view'))
        <li class="nav-item">
            <a class="nav-link {{ request()->routeIs('combos.*') ? 'active' : '' }}" href="{{ route('combos.index') }}">
                <i class="bi bi-collection"></i> Combos
            </a>
        </li>
    @endif
    @if($tabCan('packages.view'))
        <li class="nav-item">
            <a class="nav-link {{ request()->routeIs('packages.index', 'packages.create', 'packages.edit') ? 'active' : '' }}" href="{{ route('packages.index') }}">
                <i class="bi bi-ticket-perforated"></i> Paquetes
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link {{ request()->routeIs('packages.sold') ? 'active' : '' }}" href="{{ route('packages.sold') }}">
                <i class="bi bi-people"></i> Paquetes de clientes
            </a>
        </li>
    @endif
</ul>
