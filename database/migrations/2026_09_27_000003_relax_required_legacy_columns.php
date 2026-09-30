<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // El POS vende servicios: la linea puede no tener producto.
        DB::statement("ALTER TABLE `sale_details` MODIFY `product_id` BIGINT UNSIGNED NULL");
        // Quien abre la caja puede ser un usuario sin ficha de personal.
        DB::statement("ALTER TABLE `cash_sessions` MODIFY `personal_id` BIGINT UNSIGNED NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE `sale_details` MODIFY `product_id` BIGINT UNSIGNED NOT NULL");
        DB::statement("ALTER TABLE `cash_sessions` MODIFY `personal_id` BIGINT UNSIGNED NOT NULL");
    }
};
