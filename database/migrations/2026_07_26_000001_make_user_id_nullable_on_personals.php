<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // El personal puede existir sin usuario de acceso (usuario opcional).
        // Se usa SQL directo para preservar el índice UNIQUE y la FK existentes.
        DB::statement('ALTER TABLE `personals` MODIFY `user_id` BIGINT UNSIGNED NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE `personals` MODIFY `user_id` BIGINT UNSIGNED NOT NULL');
    }
};
