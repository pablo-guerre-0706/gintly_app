<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('report_definitions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')
                ->restrictOnDelete(); // dueño de la vista guardada
            $table->string('name', 120);
            $table->string('report_type', 50)->index();
            $table->json('filters')->nullable();              // rango, sucursal, categoría
            $table->boolean('is_scheduled')->default(false);
            $table->string('schedule_cron', 50)->nullable();  // frecuencia si es programado
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('report_definitions');
    }
};
