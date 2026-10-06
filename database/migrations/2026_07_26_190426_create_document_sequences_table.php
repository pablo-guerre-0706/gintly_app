<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Secuencias de folio por negocio y tipo.
return new class extends Migration
{
    public function up(): void
    {
        // Usamos el nombre 'document_sequences' de manera consistente
        if (Schema::hasTable('document_sequences')) {
            return;
        }

        Schema::create('document_sequences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->string('document_type', 50); // Ampliado a 50 caracteres para evitar truncamientos
            $table->string('prefix', 20)->nullable();
            $table->unsignedBigInteger('next_number')->default(1);
            $table->timestamps();

            // Índice único alineado con los nombres reales de las columnas
            $table->unique(['business_id', 'document_type'], 'uniq_sequence_business_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_sequences');
    }
};