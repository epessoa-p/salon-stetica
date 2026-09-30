<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // La linea de venta registra QUIEN atendio el servicio (base de la comision).
        Schema::table('sale_details', function (Blueprint $table) {
            if (!Schema::hasColumn('sale_details', 'personal_id')) {
                $table->foreignId('personal_id')->nullable()->after('service_id')->constrained('personals')->nullOnDelete();
            }
        });

        // commissions venia atada a "promoters"; se adapta al especialista (personals).
        Schema::table('commissions', function (Blueprint $table) {
            if (!Schema::hasColumn('commissions', 'personal_id')) {
                $table->foreignId('personal_id')->nullable()->after('company_id')->constrained('personals')->nullOnDelete();
            }
            if (!Schema::hasColumn('commissions', 'sale_detail_id')) {
                $table->foreignId('sale_detail_id')->nullable()->after('sale_id')->constrained('sale_details')->nullOnDelete();
            }
        });

        DB::statement("ALTER TABLE `commissions` MODIFY `promoter_id` BIGINT UNSIGNED NULL");
        DB::statement("ALTER TABLE `commissions` MODIFY `created_by` BIGINT UNSIGNED NULL");
        DB::statement("ALTER TABLE `commissions` MODIFY `status` VARCHAR(20) NOT NULL DEFAULT 'pendiente'");
    }

    public function down(): void
    {
        Schema::table('commissions', function (Blueprint $table) {
            if (Schema::hasColumn('commissions', 'sale_detail_id')) $table->dropConstrainedForeignId('sale_detail_id');
            if (Schema::hasColumn('commissions', 'personal_id')) $table->dropConstrainedForeignId('personal_id');
        });
        Schema::table('sale_details', function (Blueprint $table) {
            if (Schema::hasColumn('sale_details', 'personal_id')) $table->dropConstrainedForeignId('personal_id');
        });
    }
};
