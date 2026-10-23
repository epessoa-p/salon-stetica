<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Vence los saldos de puntos inactivos de todas las empresas (o de una).
// El vencimiento también se aplica solo al consultar el saldo de un cliente;
// este comando sirve para dejar el pasivo al día, p. ej. desde un cron diario.
Artisan::command('loyalty:expire {--company= : ID de una empresa}', function () {
    $points = app(\App\Services\LoyaltyService::class)->expireAll($this->option('company') ? (int) $this->option('company') : null);
    $this->info("Puntos vencidos por inactividad: {$points}");
})->purpose('Vence los puntos de fidelización de clientes inactivos');
