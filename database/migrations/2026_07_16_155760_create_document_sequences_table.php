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
        // create_document_sequences_table.php
        Schema::create('document_sequences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->enum('document_type', ['invoice', 'credit_note', 'sales_return']);
            $table->string('prefix', 10)->default('');
            $table->unsignedBigInteger('next_number')->default(1);
            $table->timestamps();
            $table->unique(['business_id', 'document_type']);  // una secuencia por tipo y negocio
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('document_sequences');
    }
};
