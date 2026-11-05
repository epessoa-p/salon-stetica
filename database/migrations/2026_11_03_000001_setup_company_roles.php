<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Roles propios por empresa.
 *
 * El cambio vive en database/actualizar_roles_por_empresa.sql,
 * que es el script que se ejecuta a mano en producción. La migración lo
 * reutiliza para que ambos caminos no diverjan; el script es idempotente.
 */
return new class extends Migration
{
    public function up(): void
    {
        $sql = file_get_contents(database_path('actualizar_roles_por_empresa.sql'));

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
        // Sin reverso automático: quitar la columna volvería generales los roles privados de cada empresa.
    }
};
