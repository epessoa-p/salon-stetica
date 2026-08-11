<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            // Colores base de la empresa: cabecera (topbar) y menú de navegación (sidebar).
            $table->string('primary_color', 7)->nullable()->after('logo');
            $table->string('secondary_color', 7)->nullable()->after('primary_color');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn(['primary_color', 'secondary_color']);
        });
    }
};
