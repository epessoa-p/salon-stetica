<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('payment_methods')) {
            Schema::create('payment_methods', function (Blueprint $table) {
                $table->id();
                $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
                $table->string('name', 80);
                $table->string('code', 30);
                $table->string('icon', 40)->nullable();
                $table->boolean('affects_cash')->default(true);
                $table->boolean('is_default')->default(false);
                $table->boolean('active')->default(true);
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->unique(['company_id', 'code']);
                $table->index(['company_id', 'active']);
            });
        }

        if (!Schema::hasTable('company_settings')) {
            Schema::create('company_settings', function (Blueprint $table) {
                $table->id();
                $table->foreignId('company_id')->unique()->constrained('companies')->cascadeOnDelete();

                // Moneda
                $table->string('currency', 10)->default('BOB');
                $table->string('currency_symbol', 10)->default('Bs');

                // Impuestos
                $table->decimal('tax_rate', 5, 2)->default(0);
                $table->boolean('tax_included')->default(true);
                $table->string('tax_label', 30)->default('IVA');

                // Datos de facturación
                $table->string('invoice_business_name')->nullable();
                $table->string('invoice_nit', 30)->nullable();
                $table->string('invoice_address')->nullable();
                $table->string('invoice_phone', 40)->nullable();
                $table->text('invoice_footer')->nullable();

                // Formato de folios
                $table->string('sale_prefix', 10)->default('V-');
                $table->unsignedTinyInteger('sale_padding')->default(6);

                // Operación
                $table->unsignedSmallInteger('appointment_slot_minutes')->default(30);
                $table->boolean('require_cash_session')->default(false);

                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('company_settings');
        Schema::dropIfExists('payment_methods');
    }
};
