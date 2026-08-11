<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('product_categories')) {
            Schema::create('product_categories', function (Blueprint $table) {
                $table->id();
                $table->foreignId('company_id')->constrained()->cascadeOnDelete();
                $table->string('name');
                $table->text('description')->nullable();
                $table->boolean('active')->default(true);
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (!Schema::hasTable('brands')) {
            Schema::create('brands', function (Blueprint $table) {
                $table->id();
                $table->foreignId('company_id')->constrained()->cascadeOnDelete();
                $table->string('name');
                $table->boolean('active')->default(true);
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        Schema::table('products', function (Blueprint $table) {
            if (!Schema::hasColumn('products', 'product_category_id')) {
                $table->foreignId('product_category_id')->nullable()->after('company_id')->constrained('product_categories')->nullOnDelete();
            }
            if (!Schema::hasColumn('products', 'brand_id')) {
                $table->foreignId('brand_id')->nullable()->after('product_category_id')->constrained('brands')->nullOnDelete();
            }
        });

        // El salón puede registrar movimientos sin almacén.
        DB::statement('ALTER TABLE `inventory_movements` MODIFY `warehouse_id` BIGINT UNSIGNED NULL');
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (Schema::hasColumn('products', 'brand_id')) {
                $table->dropConstrainedForeignId('brand_id');
            }
            if (Schema::hasColumn('products', 'product_category_id')) {
                $table->dropConstrainedForeignId('product_category_id');
            }
        });

        Schema::dropIfExists('brands');
        Schema::dropIfExists('product_categories');
    }
};
