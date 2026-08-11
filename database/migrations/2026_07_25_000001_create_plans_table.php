<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->decimal('price', 10, 2)->default(0);
            $table->string('currency', 10)->default('BOB');
            $table->unsignedInteger('billing_days')->default(30);
            $table->unsignedInteger('trial_days')->default(14);
            // Límites (null = ilimitado)
            $table->unsignedInteger('max_branches')->nullable();
            $table->unsignedInteger('max_personal')->nullable();
            $table->unsignedInteger('max_cajas')->nullable();
            $table->unsignedInteger('max_users')->nullable();
            $table->json('features')->nullable();
            $table->boolean('active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};
