<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            // Overrides por empresa (NULL = usar el límite del plan).
            $table->unsignedInteger('max_users_override')->nullable()->after('amount');
            $table->unsignedInteger('max_branches_override')->nullable()->after('max_users_override');
            $table->unsignedInteger('max_cajas_override')->nullable()->after('max_branches_override');
            $table->unsignedInteger('max_personal_override')->nullable()->after('max_cajas_override');
            $table->unsignedInteger('max_products_override')->nullable()->after('max_personal_override');
            // Si no es NULL, REEMPLAZA los módulos del plan para esta empresa.
            $table->json('features_override')->nullable()->after('max_products_override');
            // Días de gracia (NULL = usar config saas.grace_days).
            $table->unsignedSmallInteger('grace_days')->nullable()->after('features_override');
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn([
                'max_users_override', 'max_branches_override', 'max_cajas_override',
                'max_personal_override', 'max_products_override', 'features_override', 'grace_days',
            ]);
        });
    }
};
