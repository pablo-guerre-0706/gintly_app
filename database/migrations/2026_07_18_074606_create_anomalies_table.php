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

        // Candado de idempotencia: máximo UNA anomalía ACTIVA por (source, regla).
        // Los estados terminales (justificada, resuelta) liberan el candado → NULL en la columna.
        DB::statement("ALTER TABLE anomalies
            ADD COLUMN active_dedupe_key VARCHAR(160)
                GENERATED ALWAYS AS (
                    CASE WHEN status IN ('detectada','notificada','en_revision')
                        THEN CONCAT(anomaly_rule_id, ':', COALESCE(source_type,''), ':', COALESCE(source_id,0))
                    END
                ) VIRTUAL,
            ADD UNIQUE KEY uniq_active_anomaly (active_dedupe_key)");
        DB::statement("ALTER TABLE anomalies ADD CONSTRAINT chk_anomaly_resolution_coherence
            CHECK (status NOT IN ('justificada','resuelta') OR (resolved_by IS NOT NULL AND resolved_at IS NOT NULL))");
        }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('anomalies');
    }
};
