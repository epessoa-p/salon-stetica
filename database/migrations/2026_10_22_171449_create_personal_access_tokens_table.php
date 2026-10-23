<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tokens de Laravel Sanctum para la app móvil.
 *
 * El cambio vive en database/actualizar_api_movil.sql, que es el script que
 * se ejecuta a mano en producción. La migración lo reutiliza para que ambos
 * caminos no diverjan; el script es idempotente.
 */
return new class extends Migration
{
    public function up(): void
    {
        $sql = file_get_contents(database_path('actualizar_api_movil.sql'));

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
        Schema::dropIfExists('personal_access_tokens');
    }
};
