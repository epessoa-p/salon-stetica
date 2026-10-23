{{-- Pestañas comunes de Membresías: vendidas / planes --}}
<ul class="nav nav-tabs mb-3">
    <li class="nav-item">
        <a class="nav-link {{ request()->routeIs('memberships.index') ? 'active' : '' }}" href="{{ route('memberships.index') }}">
            <i class="bi bi-people"></i> Membresías de clientes
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link {{ request()->routeIs('memberships.plans.*') ? 'active' : '' }}" href="{{ route('memberships.plans.index') }}">
            <i class="bi bi-card-list"></i> Planes
        </a>
    </li>
</ul>
