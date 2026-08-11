<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('full_name');
            $table->string('id_number')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->date('birthdate')->nullable();
            $table->string('gender')->nullable();      // femenino | masculino | otro
            $table->string('address')->nullable();
            $table->string('source')->nullable();      // cómo llegó: recomendación, redes, etc.
            $table->text('notes')->nullable();
            $table->boolean('active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clients');
    }
};
