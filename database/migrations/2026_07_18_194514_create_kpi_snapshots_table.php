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
        Schema::create('kpi_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')
                ->restrictOnDelete(); // NULL = consolidado
            // VARCHAR, no ENUM), el job puede materializar cualquier código sin migrar el esquema
            $table->string('kpi_code', 50)->index();
            $table->enum('period_type', ['diario', 'semanal', 'mensual', 'anual'])->index();
            $table->date('period_start')->index();
            $table->date('period_end');
            $table->decimal('value', 16, 4);                    // 4 decimales: ratios, rotación
            $table->decimal('target_value', 16, 2)->nullable(); // se congela meta al calcular
            $table->decimal('achievement_pct', 7, 2)->nullable(); // value / target × 100
            $table->json('metadata')->nullable();               // desglose para el gráfico (top productos, serie)
            $table->timestamp('calculated_at');                 // cuándo corrió el job
            $table->timestamp('created_at')->nullable();        // INSERT-only
        });
        DB::statement('ALTER TABLE kpi_snapshots ADD CONSTRAINT chk_snapshot_period
            CHECK (period_end >= period_start)');
        DB::statement("
            ALTER TABLE kpi_snapshots
                ADD COLUMN branch_key BIGINT UNSIGNED GENERATED ALWAYS AS (COALESCE(branch_id, 0)) VIRTUAL,
                ADD UNIQUE KEY uniq_kpi_snapshot (business_id, branch_key, kpi_code, period_type, period_start)
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kpi_snapshots');
    }
};
