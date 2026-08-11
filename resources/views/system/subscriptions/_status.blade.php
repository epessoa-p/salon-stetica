@php
    $map = [
        'trial'   => ['Prueba', 'bg-info'],
        'active'  => ['Activa', 'bg-success'],
        'grace'   => ['En gracia', 'bg-warning text-dark'],
        'expired' => ['Vencida', 'bg-danger'],
        'canceled'=> ['Cancelada', 'bg-secondary'],
        'none'    => ['Sin plan', 'bg-secondary'],
    ];
    [$label, $class] = $map[$status] ?? ['—', 'bg-secondary'];
@endphp
<span class="badge {{ $class }}">{{ $label }}</span>
