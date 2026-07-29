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
        if (Schema::hasTable('document_sequences')) {
            return;
        }

        Schema::create('sequences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('document_business_id')->constrained('businesses')->cascadeOnDelete();
            $table->string('document_type', 40);
            $table->unsignedBigInteger('next_value')->default(1);
            $table->timestamps();

            $table->unique(['business_id', 'type'], 'uniq_sequence_business_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_sequences');
    }
};
