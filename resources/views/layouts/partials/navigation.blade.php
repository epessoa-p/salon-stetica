@php
    $u = auth()->user();
    $co = $u->getCurrentCompany();
    $navPrefix = $navPrefix ?? 'main';

    // Definición del menú por secciones (data-driven).
    // Cada ítem: label, icon, built (bool). Si built=true: route + pattern (+ visible opcional).
    // Los built=false se muestran deshabilitados con badge "Próx.".
    $sections = [
        [
            'label' => 'Clientes',
            'icon'  => 'bi-people-fill',
            'color' => '#ec4899',
            'items' => [
                ['label' => 'Clientes',    'icon' => 'bi-person-lines-fill', 'built' => true, 'route' => 'clients.index', 'pattern' => 'clients.*'],
                ['label' => 'Membresías',  'icon' => 'bi-award',             'built' => false],
                ['label' => 'Fidelización','icon' => 'bi-gift',              'built' => false],
            ],
        ],
        [
            'label' => 'Agenda',
            'icon'  => 'bi-calendar3',
            'color' => '#6366f1',
            'items' => [
                ['label' => 'Calendario',    'icon' => 'bi-calendar-week',  'built' => true, 'route' => 'appointments.calendar', 'pattern' => 'appointments.calendar'],
                ['label' => 'Citas',         'icon' => 'bi-calendar-check', 'built' => true, 'route' => 'appointments.index',    'pattern' => 'appointments.index'],
                ['label' => 'Recordatorios', 'icon' => 'bi-bell',           'built' => false],
            ],
        ],
        [
            'label' => 'Servicios',
            'icon'  => 'bi-scissors',
            'color' => '#a855f7',
            'items' => [
                ['label' => 'Servicios',        'icon' => 'bi-scissors',   'built' => true, 'route' => 'services.index',           'pattern' => 'services.*'],
                ['label' => 'Categorías',       'icon' => 'bi-tags',       'built' => true, 'route' => 'service-categories.index', 'pattern' => 'service-categories.*'],
                ['label' => 'Combos y Paquetes','icon' => 'bi-collection', 'built' => false],
            ],
        ],
        [
            'label' => 'Ventas',
            'icon'  => 'bi-cart3',
            'color' => '#14b8a6',
            'items' => [
                ['label' => 'Punto de Venta', 'icon' => 'bi-upc-scan',           'built' => true, 'route' => 'sales.pos',   'pattern' => 'sales.pos'],
                ['label' => 'Ventas',         'icon' => 'bi-receipt',            'built' => true, 'route' => 'sales.index', 'pattern' => 'sales.index'],
                ['label' => 'Cotizaciones',   'icon' => 'bi-file-earmark-text',  'built' => false],
                ['label' => 'Devoluciones',   'icon' => 'bi-arrow-return-left',  'built' => false],
            ],
        ],
        [
            'label' => 'Comisiones',
            'icon'  => 'bi-percent',
            'color' => '#0ea5a4',
            'items' => [
                ['label' => 'Comisiones', 'icon' => 'bi-percent', 'built' => true, 'route' => 'commissions.index', 'pattern' => 'commissions.*'],
            ],
        ],
        [
            'label' => 'Inventario',
            'icon'  => 'bi-box-seam',
            'color' => '#f59e0b',
            'items' => [
                ['label' => 'Productos',   'icon' => 'bi-box',      'built' => true, 'route' => 'products.index',           'pattern' => 'products.*'],
                ['label' => 'Categorías',  'icon' => 'bi-tags',     'built' => true, 'route' => 'product-categories.index', 'pattern' => 'product-categories.*'],
                ['label' => 'Marcas',      'icon' => 'bi-bookmark', 'built' => true, 'route' => 'brands.index',             'pattern' => 'brands.*'],
                ['label' => 'Existencias', 'icon' => 'bi-boxes',    'built' => true, 'route' => 'stock.index',              'pattern' => 'stock.*'],
            ],
        ],
        [
            'label' => 'Compras',
            'icon'  => 'bi-truck',
            'color' => '#f97316',
            'items' => [
                ['label' => 'Proveedores',  'icon' => 'bi-building',          'built' => true, 'route' => 'suppliers.index',            'pattern' => 'suppliers.*'],
                ['label' => 'Órdenes',      'icon' => 'bi-file-earmark-plus', 'built' => true, 'route' => 'purchase-orders.index',      'pattern' => 'purchase-orders.index'],
                ['label' => 'Recepciones',  'icon' => 'bi-box-arrow-in-down', 'built' => true, 'route' => 'purchase-orders.receptions', 'pattern' => 'purchase-orders.receptions'],
                ['label' => 'Devoluciones', 'icon' => 'bi-arrow-return-left', 'built' => false],
            ],
        ],
        [
            'label' => 'Caja y Finanzas',
            'icon'  => 'bi-cash-stack',
            'color' => '#10b981',
            'items' => [
                ['label' => 'Cajas',            'icon' => 'bi-safe',         'built' => true, 'route' => 'cajas.index', 'pattern' => 'cajas.*'],
                ['label' => 'Arqueos',          'icon' => 'bi-clipboard-check',  'built' => true, 'route' => 'cash-sessions.index', 'pattern' => 'cash-sessions.*'],
                ['label' => 'Ingresos',         'icon' => 'bi-arrow-down-circle','built' => true, 'route' => 'incomes.index',  'pattern' => 'incomes.*'],
                ['label' => 'Gastos',           'icon' => 'bi-arrow-up-circle',  'built' => true, 'route' => 'expenses.index', 'pattern' => 'expenses.*'],
                ['label' => 'Cuentas x cobrar', 'icon' => 'bi-wallet2',          'built' => false],
                ['label' => 'Cuentas x pagar',  'icon' => 'bi-credit-card',      'built' => false],
            ],
        ],
        [
            'label' => 'Reportes',
            'icon'  => 'bi-graph-up',
            'color' => '#3b82f6',
            'items' => [
                ['label' => 'Reportes', 'icon' => 'bi-bar-chart', 'built' => true, 'route' => 'reports.index', 'pattern' => 'reports.*'],
            ],
        ],
        [
            'label' => 'Marketing',
            'icon'  => 'bi-megaphone',
            'color' => '#e11d48',
            'items' => [
                ['label' => 'Campañas',    'icon' => 'bi-broadcast',          'built' => false],
                ['label' => 'Plantillas',  'icon' => 'bi-envelope-paper',     'built' => false],
                ['label' => 'Cupones',     'icon' => 'bi-ticket-perforated',  'built' => false],
                ['label' => 'Cumpleaños',  'icon' => 'bi-cake2',              'built' => false],
                ['label' => 'Promociones', 'icon' => 'bi-percent',           'built' => false],
            ],
        ],
        [
            'label' => 'Configuración',
            'icon'  => 'bi-gear',
            'color' => '#64748b',
            'items' => [
                ['label' => 'Sucursales',       'icon' => 'bi-diagram-2',            'built' => true, 'route' => 'branches.index', 'pattern' => 'branches.*'],
                ['label' => 'Empleados',        'icon' => 'bi-person-vcard',         'built' => true, 'route' => 'personal.index', 'pattern' => 'personal.*'],
                ['label' => 'Cargos',           'icon' => 'bi-briefcase',            'built' => true, 'route' => 'cargos.index',   'pattern' => 'cargos.*'],
                ['label' => 'Horarios',         'icon' => 'bi-clock',                'built' => false],
                ['label' => 'Asistencia',       'icon' => 'bi-calendar2-check',      'built' => false],
                ['label' => 'Métodos de pago',  'icon' => 'bi-credit-card-2-front',  'built' => true, 'route' => 'payment-methods.index', 'pattern' => 'payment-methods.*'],
                ['label' => 'Preferencias',     'icon' => 'bi-sliders',              'built' => true, 'route' => 'settings.edit', 'pattern' => 'settings.*'],
            ],
        ],
        [
            // Sección del operador del SaaS: se muestra completa solo al super admin.
            'label'   => 'Plataforma',
            'icon'    => 'bi-hdd-stack',
            'color'   => '#7c4dff',
            'visible' => (bool) $u->is_super_admin,
            'items'   => [
                ['label' => 'Empresas',      'icon' => 'bi-building',       'built' => true, 'route' => 'companies.index',     'pattern' => 'companies.*'],
                ['label' => 'Planes',        'icon' => 'bi-box-seam',       'built' => true, 'route' => 'plans.index',         'pattern' => 'plans.*'],
                ['label' => 'Suscripciones', 'icon' => 'bi-card-checklist', 'built' => true, 'route' => 'subscriptions.index',  'pattern' => 'subscriptions.*'],
                ['label' => 'Roles',         'icon' => 'bi-shield-lock',    'built' => true, 'route' => 'roles.index',          'pattern' => 'roles.*'],
                ['label' => 'Usuarios',      'icon' => 'bi-person-gear',    'built' => true, 'route' => 'users.index',          'pattern' => 'users.*'],
            ],
        ],
    ];
@endphp

{{-- Principal --}}
<div class="sidebar-section-title">Principal</div>
<ul class="nav flex-column gap-1 mb-2">
    <li class="nav-item">
        <a class="nav-link app-link {{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}">
            <i class="bi bi-house"></i> Dashboard
        </a>
    </li>
</ul>

{{-- Secciones colapsables --}}
@foreach($sections as $section)
    @php
        // Visibilidad explícita de la sección completa (p. ej. Plataforma: solo super admin).
        if (array_key_exists('visible', $section) && !$section['visible']) continue;

        // Oculta la sección si su módulo no está incluido en el plan (super admin siempre ve).
        $sectionModule = [
            'Clientes' => 'crm', 'Agenda' => 'scheduling', 'Servicios' => 'catalog', 'Ventas' => 'sales',
            'Inventario' => 'inventory', 'Compras' => 'purchasing', 'Caja y Finanzas' => 'finance', 'Marketing' => 'marketing',
        ][$section['label']] ?? null;
        if ($sectionModule && !$u->is_super_admin && !($co && $co->planAllows($sectionModule))) continue;

        $items = array_filter($section['items'], function ($i) use ($u, $co) {
            // Ítems "Próx." (no construidos) siempre se muestran deshabilitados.
            if (!($i['built'] ?? true)) return true;
            // Visibilidad explícita (users/roles/sistema) manda.
            if (array_key_exists('visible', $i)) return (bool) $i['visible'];
            // Deriva el permiso <módulo>.view desde el nombre de ruta.
            $module = \Illuminate\Support\Str::before($i['route'] ?? '', '.');
            $module = ['purchase-orders' => 'purchases', 'cash-sessions' => 'cash'][$module] ?? $module;
            return $u->is_super_admin || ($co && $u->hasPermissionInCompany($module . '.view', $co));
        });
        if (empty($items)) continue;
        $sectionKey = \Illuminate\Support\Str::slug($section['label']);
        $isActive = collect($items)->contains(fn ($i) => ($i['built'] ?? true) && isset($i['pattern']) && request()->routeIs($i['pattern']));
        $collapseId = 'sec-'.$navPrefix.'-'.$sectionKey;
    @endphp

    <div class="nav-section" style="--sec: {{ $section['color'] }}">
        <button class="nav-section-toggle" type="button" data-bs-toggle="collapse"
                data-bs-target="#{{ $collapseId }}" aria-expanded="{{ $isActive ? 'true' : 'false' }}">
            <span class="nav-section-label"><i class="bi {{ $section['icon'] }} me-2"></i>{{ $section['label'] }}</span>
            <i class="bi bi-chevron-down nav-chevron"></i>
        </button>
        <div class="collapse {{ $isActive ? 'show' : '' }}" id="{{ $collapseId }}"
             data-section-key="{{ $sectionKey }}" data-force-open="{{ $isActive ? '1' : '0' }}">
            <ul class="nav flex-column gap-1 nav-section-items">
                @foreach($items as $item)
                    @php $built = $item['built'] ?? true; @endphp
                    <li class="nav-item">
                        @if($built)
                            <a class="nav-link app-link {{ request()->routeIs($item['pattern']) ? 'active' : '' }}" href="{{ route($item['route']) }}">
                                <i class="bi {{ $item['icon'] }}"></i> {{ $item['label'] }}
                            </a>
                        @else
                            <span class="nav-link app-link disabled d-flex justify-content-between align-items-center" aria-disabled="true">
                                <span><i class="bi {{ $item['icon'] }}"></i> {{ $item['label'] }}</span>
                                <span class="nav-badge">Próx.</span>
                            </span>
                        @endif
                    </li>
                @endforeach
            </ul>
        </div>
    </div>
@endforeach

@once
@push('scripts')
<script>
    (function () {
        if (window.__navInit) return;
        window.__navInit = true;
        var KEY = 'navSections';
        function load() { try { return JSON.parse(localStorage.getItem(KEY)) || {}; } catch (e) { return {}; } }
        function save(s) { localStorage.setItem(KEY, JSON.stringify(s)); }
        var state = load();

        // Aplica el estado guardado (excepto secciones activas, que se dejan abiertas).
        document.querySelectorAll('.collapse[data-section-key]').forEach(function (el) {
            if (el.getAttribute('data-force-open') === '1') return;
            var k = el.getAttribute('data-section-key');
            if (Object.prototype.hasOwnProperty.call(state, k)) {
                var btn = document.querySelector('[data-bs-target="#' + el.id + '"]');
                if (state[k]) { el.classList.add('show'); } else { el.classList.remove('show'); }
                if (btn) btn.setAttribute('aria-expanded', state[k] ? 'true' : 'false');
            }
        });

        // Guarda el estado al abrir/cerrar (varias secciones pueden estar abiertas).
        document.addEventListener('shown.bs.collapse', function (e) {
            var k = e.target.getAttribute && e.target.getAttribute('data-section-key');
            if (k) { state[k] = true; save(state); }
        });
        document.addEventListener('hidden.bs.collapse', function (e) {
            var k = e.target.getAttribute && e.target.getAttribute('data-section-key');
            if (k) { state[k] = false; save(state); }
        });
    })();
</script>
@endpush
@endonce
