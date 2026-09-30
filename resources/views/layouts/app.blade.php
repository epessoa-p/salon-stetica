@extends('layouts.base')

@section('content')
@php
    $currentCompany = auth()->user()->getCurrentCompany();
    $activeCompanies = auth()->user()->activeCompanies()->get();

    // Colores base de la empresa activa (con fallback al tema por defecto).
    $headerBg = $currentCompany?->primary_color ?: '#1e1e1e';
    $navBg    = $currentCompany?->secondary_color ?: '#f8f8f8';

    // Texto legible según luminancia del fondo.
    $contrastText = function (string $hex): string {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }
        if (strlen($hex) !== 6) {
            return '#ffffff';
        }
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
        $luminance = (0.299 * $r + 0.587 * $g + 0.114 * $b) / 255;
        return $luminance > 0.6 ? '#1b1b1b' : '#ffffff';
    };

    $headerText = $contrastText($headerBg);
    $navText    = $contrastText($navBg);

    // Aclara (%>0) u oscurece (%<0) un color para generar degradados.
    $shade = function (string $hex, int $percent): string {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }
        if (strlen($hex) !== 6) {
            return '#'.$hex;
        }
        $ch = function (int $c) use ($percent): int {
            $v = $percent >= 0 ? $c + (255 - $c) * ($percent / 100) : $c * (1 + $percent / 100);
            return (int) max(0, min(255, round($v)));
        };
        return sprintf('#%02x%02x%02x', $ch(hexdec(substr($hex, 0, 2))), $ch(hexdec(substr($hex, 2, 2))), $ch(hexdec(substr($hex, 4, 2))));
    };

    $headerGradA = $shade($headerBg, 14);
    $headerGradB = $shade($headerBg, -16);
    $navGradA    = $shade($navBg, 7);
    $navGradB    = $shade($navBg, -5);
@endphp

<style>
    :root {
        --company-header-bg: {{ $headerBg }};
        --company-header-text: {{ $headerText }};
        --company-header-grad: linear-gradient(120deg, {{ $headerGradA }} 0%, {{ $headerBg }} 45%, {{ $headerGradB }} 100%);
        --company-nav-bg: {{ $navBg }};
        --company-nav-text: {{ $navText }};
        --company-nav-grad: linear-gradient(180deg, {{ $navGradA }} 0%, {{ $navBg }} 55%, {{ $navGradB }} 100%);
        /* Acento: el color de la cabecera se reutiliza en el menú activo */
        --company-accent: {{ $headerBg }};
        --company-accent-text: {{ $headerText }};
    }
</style>

<div class="app-shell d-flex">
    <aside class="app-sidebar">
        <div class="sidebar-brand">
            <button class="btn btn-link p-0 me-2 d-lg-none text-dark" type="button" data-bs-toggle="offcanvas" data-bs-target="#appSidebarMobile" aria-controls="appSidebarMobile">
                <i class="bi bi-list fs-4"></i>
            </button>
            @if($currentCompany)
                @if($currentCompany->logo)
                    <div class="brand-logo"><img src="{{ asset('storage/'.$currentCompany->logo) }}" alt="{{ $currentCompany->name }}"></div>
                @else
                    <div class="brand-icon"><i class="bi bi-shop"></i></div>
                @endif
                <div>
                    <div class="brand-title">{{ $currentCompany->name }}</div>
                </div>
            @else
                <div class="brand-logo brand-logo-iso"><img src="{{ asset('images/brand/glamora-isotipo-sm.png') }}" alt="Glamora" onerror="this.style.display='none'"></div>
                <div>
                    <div class="brand-title">Glamora</div>
                    <small class="text-muted">Modo Global</small>
                </div>
            @endif
        </div>

        @include('layouts.partials.navigation', ['navPrefix' => 'desktop'])
    </aside>

    <main class="app-main">
        <nav class="navbar navbar-expand-lg app-topbar mb-4">
            <div class="container-fluid px-0">
                <div class="d-flex align-items-center gap-3">
                    <button class="btn btn-icon d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#appSidebarMobile" aria-controls="appSidebarMobile">
                        <i class="bi bi-list"></i>
                    </button>
                    @php
                        $authUser = auth()->user();
                        $userCargo = $authUser->is_super_admin
                            ? 'Super Administrador'
                            : ($authUser->personal?->cargo?->name ?? 'Sin cargo');
                    @endphp
                    <span class="topbar-label"><i class="bi bi-person-badge me-1"></i>{{ $userCargo }}</span>
                    <span class="topbar-separator">|</span>
                    <span class="text-muted small">{{ $authUser->name }}</span>

                    @if(!auth()->user()->is_super_admin && $activeCompanies->count() > 1)
                        <div class="dropdown">
                            <button class="btn btn-outline-light btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="bi bi-buildings"></i> Empresa
                            </button>
                            <ul class="dropdown-menu shadow border-0">
                                @foreach($activeCompanies as $company)
                                    <li>
                                        <form action="{{ route('set-company', $company->id) }}" method="POST">
                                            @csrf
                                            <button type="submit" class="dropdown-item d-flex justify-content-between align-items-center">
                                                <span>{{ $company->name }}</span>
                                                @if($currentCompany && $currentCompany->id === $company->id)
                                                    <i class="bi bi-check-lg text-success"></i>
                                                @endif
                                            </button>
                                        </form>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                </div>

                <div class="d-flex align-items-center gap-2">
                    <div class="dropdown">
                        <button class="btn btn-icon" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-person-circle"></i>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end shadow border-0">
                            <li><span class="dropdown-item-text text-muted">{{ auth()->user()->name }}</span></li>
                            <li><span class="dropdown-item-text text-muted small">{{ auth()->user()->email }}</span></li>
                        </ul>
                    </div>
                    <form action="{{ route('logout') }}" method="POST" class="m-0">
                        @csrf
                        <button class="btn btn-logout" type="submit">
                            <i class="bi bi-box-arrow-right"></i> Cerrar sesion
                        </button>
                    </form>
                </div>
            </div>
        </nav>

        @if(isset($breadcrumbs))
            <nav aria-label="breadcrumb" class="mb-4">
                <ol class="breadcrumb">
                    @foreach($breadcrumbs as $label => $url)
                        @if($loop->last)
                            <li class="breadcrumb-item active">{{ $label }}</li>
                        @else
                            <li class="breadcrumb-item"><a href="{{ $url }}" class="text-decoration-none">{{ $label }}</a></li>
                        @endif
                    @endforeach
                </ol>
            </nav>
        @endif

        @if($message = session('success'))
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle"></i> {{ $message }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        @if($message = session('error'))
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-circle"></i> {{ $message }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        @include('layouts.partials.subscription-banner')

        @yield('page')
    </main>
</div>

<div class="offcanvas offcanvas-start" tabindex="-1" id="appSidebarMobile" aria-labelledby="appSidebarMobileLabel">
    <div class="offcanvas-header">
        <h5 class="offcanvas-title" id="appSidebarMobileLabel">Menu</h5>
        <button type="button" class="btn-close {{ $navText === '#ffffff' ? 'btn-close-white' : '' }}" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body p-0">
        <nav class="p-3">
            @include('layouts.partials.navigation', ['navPrefix' => 'mobile'])
        </nav>
    </div>
</div>

@push('styles')
<style>
    .app-shell {
        min-height: 100vh;
        background: #f2f2f2;
    }

    .app-sidebar {
        width: 250px;
        flex: 0 0 250px;
        background: var(--company-nav-grad);
        color: var(--company-nav-text);
        border-right: 1px solid color-mix(in srgb, var(--company-nav-text) 12%, transparent);
        box-shadow: inset -8px 0 20px -18px rgba(0, 0, 0, 0.5);
        padding: 12px 10px 24px;
        position: sticky;
        top: 0;
        /* align-self evita que flex estire el elemento y rompa el sticky */
        align-self: flex-start;
        height: 100vh;
        /* Si el menú es más alto que la ventana, scrollea dentro de la barra
           (antes los últimos ítems se salían del fondo) */
        overflow-y: auto;
        overscroll-behavior: contain;
    }

    /* Scrollbar discreta, en el tono del menú */
    .app-sidebar {
        scrollbar-width: thin;
        scrollbar-color: color-mix(in srgb, var(--company-nav-text) 25%, transparent) transparent;
    }

    .app-sidebar::-webkit-scrollbar {
        width: 6px;
    }

    .app-sidebar::-webkit-scrollbar-track {
        background: transparent;
    }

    .app-sidebar::-webkit-scrollbar-thumb {
        background: color-mix(in srgb, var(--company-nav-text) 22%, transparent);
        border-radius: 8px;
    }

    .app-sidebar::-webkit-scrollbar-thumb:hover {
        background: color-mix(in srgb, var(--company-nav-text) 38%, transparent);
    }

    .sidebar-brand {
        display: flex;
        align-items: center;
        gap: 10px;
        margin-bottom: 18px;
        padding: 10px 8px 14px;
        border-bottom: 1px solid color-mix(in srgb, var(--company-nav-text) 15%, transparent);
    }

    .brand-icon {
        width: 32px;
        height: 32px;
        border-radius: 8px;
        display: grid;
        place-items: center;
        color: var(--company-accent-text);
        background: var(--company-header-grad);
        box-shadow: 0 3px 8px color-mix(in srgb, var(--company-accent) 45%, transparent);
        font-size: 0.9rem;
    }

    .brand-logo {
        width: 38px;
        height: 38px;
        border-radius: 8px;
        overflow: hidden;
        flex-shrink: 0;
        background: #fff;
        box-shadow: 0 2px 6px rgba(0, 0, 0, 0.15);
        display: grid;
        place-items: center;
    }

    .brand-logo img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    /* Isotipo de marca (PNG transparente): se muestra completo, sin fondo */
    .brand-logo-iso {
        background: transparent;
        box-shadow: none;
    }

    .brand-logo-iso img {
        object-fit: contain;
    }

    .brand-title {
        font-weight: 700;
        font-size: 0.9rem;
        letter-spacing: 0.02em;
        line-height: 1.15;
        color: var(--company-nav-text);
    }

    .app-shell .app-sidebar .brand-title {
        text-transform: none;
    }

    .sidebar-brand small {
        color: color-mix(in srgb, var(--company-nav-text) 60%, transparent) !important;
    }

    .sidebar-section-title {
        color: color-mix(in srgb, var(--company-nav-text) 60%, transparent);
        font-size: 0.75rem;
        font-weight: 700;
        text-transform: none;
        letter-spacing: 0;
        padding: 2px 10px 6px;
    }

    /* Secciones del menú tipo tarjeta con color (una por sección vía --sec) */
    .nav-section {
        margin: 0 2px 10px;
        border-radius: 12px;
        overflow: hidden;
        background: #ffffff;
        border: 1px solid color-mix(in srgb, var(--sec) 22%, transparent);
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
    }

    .nav-section-toggle {
        width: 100%;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 8px;
        border: 0;
        padding: 11px 13px;
        font-size: 0.74rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        color: color-mix(in srgb, var(--sec) 78%, #1b1b1b);
        background: color-mix(in srgb, var(--sec) 12%, #ffffff);
        transition: background 0.2s ease, color 0.2s ease;
    }

    /* Sección abierta: encabezado a todo color con texto blanco */
    .nav-section-toggle[aria-expanded="true"] {
        color: #ffffff;
        background: linear-gradient(135deg, color-mix(in srgb, var(--sec) 88%, #ffffff) 0%, var(--sec) 100%);
    }

    .nav-section-toggle:hover {
        filter: brightness(0.97);
    }

    .nav-section-label {
        display: flex;
        align-items: center;
    }

    .nav-chevron {
        font-size: 0.7rem;
        transition: transform 0.2s ease;
    }

    .nav-section-toggle[aria-expanded="true"] .nav-chevron {
        transform: rotate(180deg);
    }

    /* Cuerpo de la sección: fondo blanco, ítems legibles con acento de la sección */
    .nav-section .collapse,
    .nav-section .collapsing {
        background: #ffffff;
    }

    .nav-section-items {
        padding: 6px;
        margin: 0;
    }

    .nav-section .app-link {
        color: #4b5563;
        padding: 8px 10px;
    }

    .nav-section .app-link i {
        color: var(--sec);
    }

    .nav-section .app-link:hover {
        background: color-mix(in srgb, var(--sec) 10%, #ffffff);
        color: #111827;
        transform: none;
    }

    .nav-section .app-link.active {
        background: color-mix(in srgb, var(--sec) 14%, #ffffff);
        color: var(--sec);
        border-color: transparent;
        box-shadow: none;
        font-weight: 600;
    }

    .nav-section .app-link.active i {
        color: var(--sec);
    }

    /* Ítem no construido: deshabilitado con badge "Próx." */
    .nav-section .app-link.disabled {
        color: #9ca3af;
        cursor: default;
        opacity: 0.85;
    }

    .nav-section .app-link.disabled:hover {
        background: transparent;
        color: #9ca3af;
    }

    .nav-section .app-link.disabled i {
        color: #c4c4c4;
    }

    .nav-badge {
        font-size: 0.6rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.03em;
        color: #9ca3af;
        background: #eef0f2;
        border-radius: 10px;
        padding: 2px 7px;
    }

    .app-link {
        position: relative;
        border-radius: 8px;
        padding: 9px 12px;
        color: color-mix(in srgb, var(--company-nav-text) 82%, transparent);
        border: 1px solid transparent;
        font-size: 0.88rem;
        transition: all 0.18s ease;
    }

    .app-link:hover {
        background: color-mix(in srgb, var(--company-nav-text) 10%, transparent);
        color: var(--company-nav-text);
        transform: translateX(2px);
    }

    .app-link.active {
        background: var(--company-header-grad);
        border-color: transparent;
        color: var(--company-accent-text);
        font-weight: 600;
        box-shadow: 0 6px 16px -4px color-mix(in srgb, var(--company-accent) 55%, transparent);
    }

    .app-link.active:hover {
        color: var(--company-accent-text);
        transform: none;
    }

    .app-link.active i {
        color: var(--company-accent-text);
    }

    /* Menú móvil (offcanvas): hereda los colores del menú de navegación */
    #appSidebarMobile {
        background: var(--company-nav-grad);
        color: var(--company-nav-text);
    }

    #appSidebarMobile .offcanvas-title {
        color: var(--company-nav-text);
    }

    #appSidebarMobile .offcanvas-header {
        border-bottom: 1px solid color-mix(in srgb, var(--company-nav-text) 15%, transparent);
    }

    .app-main {
        flex: 1;
        padding: 14px 18px 24px;
    }

    .app-topbar {
        background: var(--company-header-grad);
        color: var(--company-header-text);
        border: 0;
        border-radius: 0;
        padding: 10px 16px;
        margin-left: -18px;
        margin-right: -18px;
        margin-top: -14px;
        box-shadow: 0 4px 18px -6px rgba(0, 0, 0, 0.35);
    }

    .topbar-label {
        color: var(--company-header-text);
        font-size: 0.82rem;
        font-weight: 500;
    }

    .topbar-separator {
        color: color-mix(in srgb, var(--company-header-text) 55%, transparent);
        font-size: 0.8rem;
    }

    .app-topbar .text-muted {
        color: color-mix(in srgb, var(--company-header-text) 70%, transparent) !important;
    }

    .app-topbar .btn-outline-light {
        color: var(--company-header-text);
        border-color: color-mix(in srgb, var(--company-header-text) 35%, transparent);
    }

    .app-topbar .btn-outline-light:hover {
        background: color-mix(in srgb, var(--company-header-text) 18%, transparent);
        color: var(--company-header-text);
    }

    .btn-icon {
        width: 34px;
        height: 34px;
        border-radius: 8px;
        border: 1px solid color-mix(in srgb, var(--company-header-text) 25%, transparent);
        background: color-mix(in srgb, var(--company-header-text) 14%, transparent);
        color: var(--company-header-text);
        display: grid;
        place-items: center;
        padding: 0;
    }

    .btn-icon:hover {
        background: color-mix(in srgb, var(--company-header-text) 24%, transparent);
        color: var(--company-header-text);
    }

    .btn-logout {
        border: 1px solid color-mix(in srgb, var(--company-header-text) 30%, transparent);
        background: color-mix(in srgb, var(--company-header-text) 14%, transparent);
        color: var(--company-header-text);
        border-radius: 8px;
        padding: 6px 10px;
        font-size: 0.82rem;
    }

    .btn-logout:hover {
        background: color-mix(in srgb, var(--company-header-text) 24%, transparent);
        color: var(--company-header-text);
    }

    @media (max-width: 991.98px) {
        .app-sidebar {
            display: none;
        }

        .app-main {
            padding: 16px;
        }

        .app-topbar {
            margin-left: -16px;
            margin-right: -16px;
            margin-top: -16px;
        }
    }
</style>
@endpush
@endsection
