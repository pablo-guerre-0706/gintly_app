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
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->string('name', 160)->index();
            $table->string('tax_id', 30)->nullable();
            $table->string('email', 180)->nullable();
            $table->string('phone', 30)->nullable();

            // Compuerta de aprobación: solo 'aprobado' admite OC (validación en Service)
            $table->enum('status', ['pendiente', 'aprobado', 'suspendido'])->default('pendiente')->index();

            // Quién aprobó (ROL-01). No borras al usuario que dejó su firma de aprobación
            $table->foreignId('approved_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('approved_at')->nullable();

            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            // tax_id único por negocio. Clave: los NULL no colisionan entre sí en MySQL,
            // así admites varios proveedores sin RUC sin violar el UNIQUE.
            $table->unique(['business_id', 'tax_id']);
            $table->index('deleted_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('suppliers');
    }
};
