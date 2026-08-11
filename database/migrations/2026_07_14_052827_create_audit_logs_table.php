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
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();

            // RESTRICT, no puedes borrar un negocio con historial de auditoría
            $table->foreignId('business_id')->constrained('businesses')->restrictOnDelete();

            // Responsable, NULL si es ROL-SYS. RESTRICT preserva la atribución
            $table->foreignId('user_id')->nullable()->constrained('users')->restrictOnDelete();

            $table->string('action', 100)->index();                // 'sale.created', 'cash.closed'

            // Morph manual: type NOT NULL, id NULL (nullableMorphs haría ambos null, no sirve)
            $table->string('auditable_type', 120);
            $table->unsignedBigInteger('auditable_id')->nullable()->index();

            $table->json('old_values')->nullable();                // est. ant.
            $table->json('new_values')->nullable();                // est. nuevo
            $table->string('ip_address', 45)->nullable();          // soporta IPv6

            // Solo created_at. No timestamps(), no softDeletes. No modificable
            $table->timestamp('created_at')->nullable()->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
