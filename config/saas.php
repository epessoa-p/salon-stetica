<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Configuración SaaS (suscripciones y planes)
    |--------------------------------------------------------------------------
    */

    // Días de gracia después del vencimiento antes de bloquear el acceso.
    'grace_days' => (int) env('SAAS_GRACE_DAYS', 5),

    // Días de prueba por defecto si un plan no define trial_days.
    'default_trial_days' => (int) env('SAAS_TRIAL_DAYS', 14),

    // Moneda por defecto para planes y pagos.
    'currency' => env('SAAS_CURRENCY', 'BOB'),

    // Métodos de pago manuales admitidos.
    'payment_methods' => [
        'transferencia' => 'Transferencia bancaria',
        'qr'            => 'Pago QR',
        'efectivo'      => 'Efectivo',
    ],
];
