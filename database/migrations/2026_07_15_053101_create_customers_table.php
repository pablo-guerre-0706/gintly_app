<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->string('name', 160)->index();

            // 'generico' marca al cliente al paso; DEFAULT 'cedula' para clientes reales
            $table->enum('document_type', ['cedula', 'ruc', 'pasaporte', 'generico'])->default('cedula');
            $table->string('document_number', 30)->nullable();
            $table->string('email', 180)->nullable();
            $table->string('phone_number', 30)->nullable()->index();
            $table->date('birth_date')->nullable();               // base de campañas Fase 2
            $table->boolean('is_generic')->default(false)->index(); // TRUE solo en el "Consumidor Final"
            $table->boolean('is_active')->default(true);
            $table->decimal('credit_limit', 14, 2)->default(0);   // 0 = no accede a crédito
            $table->string('notes', 500)->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Documento único por negocio. Los NULL múltiples conviven (genérico + clientes s/cédula):
            // MySQL no considera dos NULL "iguales" en un índice UNIQUE.
            $table->unique(['business_id', 'document_number']);
            $table->index('deleted_at');
        });

        DB::statement("ALTER TABLE customers
            ADD COLUMN generic_lock BIGINT UNSIGNED
                GENERATED ALWAYS AS (CASE WHEN is_generic = 1 THEN business_id END) VIRTUAL,
            ADD UNIQUE KEY uniq_generic_customer_per_business (generic_lock)");
        DB::statement('ALTER TABLE customers ADD CONSTRAINT chk_customer_credit_limit_non_negative
            CHECK (credit_limit >= 0)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
