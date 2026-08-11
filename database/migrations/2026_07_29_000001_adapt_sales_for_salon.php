<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            if (!Schema::hasColumn('sales', 'client_id')) {
                $table->foreignId('client_id')->nullable()->after('branch_id')->constrained('clients')->nullOnDelete();
            }
        });

        Schema::table('sale_details', function (Blueprint $table) {
            if (!Schema::hasColumn('sale_details', 'service_id')) {
                $table->foreignId('service_id')->nullable()->after('product_id')->constrained('services')->nullOnDelete();
            }
            if (!Schema::hasColumn('sale_details', 'description')) {
                $table->string('description')->nullable()->after('service_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            if (Schema::hasColumn('sales', 'client_id')) {
                $table->dropConstrainedForeignId('client_id');
            }
        });

        Schema::table('sale_details', function (Blueprint $table) {
            if (Schema::hasColumn('sale_details', 'service_id')) {
                $table->dropConstrainedForeignId('service_id');
            }
            if (Schema::hasColumn('sale_details', 'description')) {
                $table->dropColumn('description');
            }
        });
    }
};
