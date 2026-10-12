<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Módulo de comisiones: reglas, devengo trazable y liquidaciones.
 *
 * El cambio vive en database/actualizar_modulo_comisiones.sql, que es el
 * script que se ejecuta a mano en producción. La migración lo reutiliza para
 * que ambos caminos no diverjan; el script es idempotente, así que correrlo
 * sobre una base que ya lo tiene no cambia nada.
 */
return new class extends Migration
{
    public function up(): void
    {
        $sql = file_get_contents(database_path('actualizar_modulo_comisiones.sql'));

        // Quita los comentarios de línea y separa las sentencias.
        $sql = preg_replace('/^\s*--.*$/m', '', $sql);

        foreach (preg_split('/;\s*/', $sql) as $statement) {
            if (trim($statement) !== '') {
                DB::unprepared($statement);
            }
        }
    }

    public function down(): void
    {
        // Sin reverso automático: el módulo guarda importes pagados y
        // liquidaciones que no deben perderse por un rollback.
    }
};
