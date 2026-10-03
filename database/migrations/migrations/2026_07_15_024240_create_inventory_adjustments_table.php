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
        Schema::create('inventory_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();

            // Opcional: si el ajuste nace de una conciliación. physical_counts ya existe en este módulo
            $table->foreignId('physical_count_id')->nullable()
                  ->constrained('physical_counts')->restrictOnDelete();

            $table->enum('type', ['merma', 'sobrante', 'correccion'])->index();
            $table->string('reason', 255);   // motivo obligatorio, nada sin justificar
            $table->timestamp('adjusted_at');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_adjustments');
    }
};
