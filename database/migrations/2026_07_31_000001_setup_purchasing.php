<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('suppliers')) {
            Schema::create('suppliers', function (Blueprint $table) {
                $table->id();
                $table->foreignId('company_id')->constrained()->cascadeOnDelete();
                $table->string('name');
                $table->string('contact_name')->nullable();
                $table->string('phone')->nullable();
                $table->string('email')->nullable();
                $table->string('tax_id')->nullable();
                $table->string('address')->nullable();
                $table->text('notes')->nullable();
                $table->boolean('active')->default(true);
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        Schema::table('entries', function (Blueprint $table) {
            if (!Schema::hasColumn('entries', 'supplier_id')) {
                $table->foreignId('supplier_id')->nullable()->after('company_id')->constrained('suppliers')->nullOnDelete();
            }
        });

        // El salón puede registrar órdenes/entradas sin almacén.
        DB::statement('ALTER TABLE `entries` MODIFY `warehouse_id` BIGINT UNSIGNED NULL');
    }

    public function down(): void
    {
        Schema::table('entries', function (Blueprint $table) {
            if (Schema::hasColumn('entries', 'supplier_id')) {
                $table->dropConstrainedForeignId('supplier_id');
            }
        });

        Schema::dropIfExists('suppliers');
    }
};
