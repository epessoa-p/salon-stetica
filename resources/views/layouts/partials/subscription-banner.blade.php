@php
    $bannerCompany = auth()->user()->is_super_admin ? null : auth()->user()->getCurrentCompany();
    $bannerSub = $bannerCompany?->currentSubscription();
    $bannerStatus = $bannerSub?->computedStatus();
    $bannerDays = $bannerSub?->daysLeft();
@endphp

@if($bannerSub && in_array($bannerStatus, ['trial', 'grace'], true))
    <div class="alert {{ $bannerStatus === 'grace' ? 'alert-warning' : 'alert-info' }} d-flex align-items-center gap-2 mb-4">
        <i class="bi {{ $bannerStatus === 'grace' ? 'bi-exclamation-triangle-fill' : 'bi-info-circle-fill' }}"></i>
        <div class="small">
            @if($bannerStatus === 'trial')
                Estás en <strong>periodo de prueba</strong>.
                @if($bannerDays !== null) Quedan <strong>{{ max($bannerDays, 0) }} día(s)</strong> de prueba. @endif
            @else
                Tu suscripción <strong>venció</strong> y estás en periodo de gracia.
                @if($bannerDays !== null) Se bloqueará en <strong>{{ max($bannerDays + (int) config('saas.grace_days'), 0) }} día(s)</strong> aprox. @endif
                Renueva para no perder el acceso.
            @endif
        </div>
    </div>
@endif
