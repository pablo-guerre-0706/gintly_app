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
        Schema::create('customer_addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();

            // Excepción justificada a RESTRICT. La dirección no tiene sentido sin cliente → CASCADE.
            // Con SoftDeletes en customers, solo actúa en un forceDelete administrativo.
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();

            $table->string('label', 50);            // 'Casa', 'Oficina'
            $table->string('address_line', 255);
            $table->string('reference', 255)->nullable();
            $table->boolean('is_default')->default(false); // "una principal por cliente" se cuida en la app
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_addresses');
    }
};
