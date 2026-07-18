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
        Schema::create('anomalies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->foreignId('anomaly_rule_id')->constrained('anomaly_rules')->restrictOnDelete();
            // NULL si nació de un evento en tiempo real, no de una corrida
            $table->foreignId('reconciliation_run_id')->nullable()->constrained('reconciliation_runs')
                ->restrictOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->restrictOnDelete();

            $table->enum('severity', ['informativa', 'advertencia', 'critica'])->index();
            $table->enum('status', ['detectada', 'notificada', 'en_revision', 'justificada', 'resuelta'])->index();

            $table->decimal('expected_value', 14, 2)->nullable(); // lo que el sistema esperaba
            $table->decimal('actual_value', 14, 2)->nullable();   // lo que realmente había
            $table->decimal('difference', 14, 2)->nullable();     // discrepancia cuantificada (±)

            // PUNTERO DÉBIL deliberado (sin constrained()): el origen es informativo y heterogéneo
            // Distinto del origen transaccional de inventory_movements, que sí exigía FK. Criterio, no recaída.
            $table->string('source_type', 60)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();

            $table->foreignId('resolved_by')->nullable()->constrained('users')->restrictOnDelete(); // No el causante
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('detected_at');
            $table->timestamps();

            // Índice compuesto: soporta la navegación y el lookup de idempotencia del cron híbrido
            $table->index(['source_type', 'source_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('anomalies');
    }
};
