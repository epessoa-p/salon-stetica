<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Combos y paquetes, cotizaciones, devoluciones (clientes y proveedores) y
 * saldo a favor.
 *
 * El cambio vive en database/actualizar_devoluciones_cotizaciones_combos.sql,
 * que es el script que se ejecuta a mano en producción. La migración lo
 * reutiliza para que ambos caminos no diverjan; el script es idempotente.
 */
return new class extends Migration
{
    public function up(): void
    {
        $sql = file_get_contents(database_path('actualizar_devoluciones_cotizaciones_combos.sql'));

        // Quita los comentarios de línea y separa las sentencias.
        $sql = preg_replace('/^\s*--.*$/m', '', $sql);

        foreach (preg_split('/;\s*/', $sql) as $statement) {
            // El registro en `migrations` es para producción: aquí lo hace el propio migrador.
            if (trim($statement) !== '' && !str_contains($statement, 'INSERT INTO `migrations`')) {
                DB::unprepared($statement);
            }
        }
    }

    public function down(): void
    {
        // Sin reverso automático: guarda devoluciones, cotizaciones, paquetes
        // vendidos y saldos a favor que no deben perderse por un rollback.
    }
};
