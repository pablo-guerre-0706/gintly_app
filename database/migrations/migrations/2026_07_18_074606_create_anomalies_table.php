<?php

declare(strict_types=1);

use App\Enums\AnomalySeverity;
use App\Enums\AnomalyStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('anomalies')) {
            return;
        }

        Schema::create('anomalies', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->foreignId('anomaly_rule_id')->constrained('anomaly_rules')->restrictOnDelete();
            $table->foreignId('reconciliation_run_id')->nullable()->constrained('reconciliation_runs')->restrictOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->restrictOnDelete();

            $table->enum('severity', AnomalySeverity::values())->index('idx_anomaly_severity');
            $table->enum('status', AnomalyStatus::values())->index('idx_anomaly_status');

            $table->decimal('expected_value', 14, 2)->nullable(); // bcmath.
            $table->decimal('actual_value', 14, 2)->nullable();
            $table->decimal('difference', 14, 2)->nullable();

            // Puntero polimórfico DÉBIL (sin FK, fuera del MorphMap): source_type = nombre de tabla.
            $table->string('source_type', 60)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();

            $table->foreignId('resolved_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('detected_at');

            $table->timestamps();

            $table->index(['source_type', 'source_id'], 'idx_anomaly_source');

            // Idempotencia estructural (RF-11-08): clave 'regla:origen' SOLO mientras está activa.
            // En 'justificada'/'resuelta' la expresión da NULL y libera el candado (los NULL no colisionan).
            $table->string('active_dedupe_key', 160)->virtualAs(
                "CASE WHEN `status` IN ('detectada','notificada','en_revision') "
                . "THEN CONCAT(`anomaly_rule_id`, ':', COALESCE(`source_type`, ''), ':', COALESCE(`source_id`, 0)) "
                . 'ELSE NULL END'
            );
            $table->unique('active_dedupe_key', 'uniq_active_anomaly');
        });

        // Coherencia de cierre: justificada/resuelta ⇒ validador y marca temporal presentes.
        DB::statement(
            'ALTER TABLE `anomalies` ADD CONSTRAINT `chk_anomaly_resolution_coherence` '
            . "CHECK (`status` NOT IN ('justificada','resuelta') OR (`resolved_by` IS NOT NULL AND `resolved_at` IS NOT NULL))"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('anomalies');
    }
};
