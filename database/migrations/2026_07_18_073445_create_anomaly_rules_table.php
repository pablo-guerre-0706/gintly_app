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
        Schema::create('anomaly_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            // Catálogo fijo definido por el sistema
            $table->enum('code', [
                'descuadre_caja', 'faltante_inventario', 'discrepancia_3way',
                'cuenta_vencida', 'omision_registro', 'venta_sin_sesion',
            ])->index();
            $table->string('name', 120);
            $table->decimal('threshold_value', 14, 2)->nullable(); // nullable, hay reglas sin umbral numérico
            $table->enum('threshold_type', ['monto', 'porcentaje', 'cantidad', 'tiempo']);
            $table->enum('default_severity', ['informativa', 'advertencia', 'critica']);
            $table->boolean('is_active')->default(true); // el dueño puede desactivar una regla
            $table->timestamps();
            $table->unique(['business_id', 'code']); // una regla por tipo, por negocio
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('anomaly_rules');
    }
};
