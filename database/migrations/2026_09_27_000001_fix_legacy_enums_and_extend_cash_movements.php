<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1) Los ENUM heredados estaban en inglés y el sistema usa valores en español
        //    (ingreso/egreso, completada/anulada, entrada/salida/ajuste, pendiente/recibida...).
        //    Se convierten a VARCHAR para admitir el vocabulario del dominio.
        DB::statement("ALTER TABLE `cash_movements` MODIFY `type` VARCHAR(20) NOT NULL");
        DB::statement("ALTER TABLE `cash_movements` MODIFY `payment_method` VARCHAR(30) NOT NULL DEFAULT 'efectivo'");
        DB::statement("ALTER TABLE `sales` MODIFY `payment_method` VARCHAR(30) NOT NULL DEFAULT 'efectivo'");
        DB::statement("ALTER TABLE `sales` MODIFY `status` VARCHAR(20) NOT NULL DEFAULT 'completada'");
        DB::statement("ALTER TABLE `inventory_movements` MODIFY `type` VARCHAR(20) NOT NULL");
        DB::statement("ALTER TABLE `entries` MODIFY `status` VARCHAR(20) NOT NULL DEFAULT 'pendiente'");

        // 2) cash_movements pasa a ser el libro de movimientos de dinero de la empresa:
        //    puede existir sin sesión de caja (ingresos/gastos sueltos) y se aísla por empresa.
        Schema::table('cash_movements', function (Blueprint $table) {
            if (!Schema::hasColumn('cash_movements', 'company_id')) {
                $table->foreignId('company_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
            }
            if (!Schema::hasColumn('cash_movements', 'category')) {
                $table->string('category')->nullable()->after('concept');
            }
            if (!Schema::hasColumn('cash_movements', 'movement_date')) {
                $table->date('movement_date')->nullable()->after('category');
            }
        });

        DB::statement("ALTER TABLE `cash_movements` MODIFY `cash_session_id` BIGINT UNSIGNED NULL");
        DB::statement("ALTER TABLE `cash_movements` MODIFY `created_by` BIGINT UNSIGNED NULL");
    }

    public function down(): void
    {
        Schema::table('cash_movements', function (Blueprint $table) {
            if (Schema::hasColumn('cash_movements', 'company_id')) $table->dropConstrainedForeignId('company_id');
            if (Schema::hasColumn('cash_movements', 'category')) $table->dropColumn('category');
            if (Schema::hasColumn('cash_movements', 'movement_date')) $table->dropColumn('movement_date');
        });
    }
};
