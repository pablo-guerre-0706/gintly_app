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
        Schema::create('business_goals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')
                ->restrictOnDelete(); // NULL = meta global
            // kpi_code como ENUM: las metas SÍ se atan al catálogo comprometido
            $table->enum('kpi_code', [
                'ventas', 'margen', 'ticket_promedio',
                'cumplimiento_registro', 'recuperacion_cartera', 'rotacion_inventario',
            ])->index();
            $table->enum('period_type', ['diario', 'semanal', 'mensual', 'anual'])->index();
            $table->date('period_start');
            $table->date('period_end');
            $table->decimal('target_value', 16, 2); // CHECK > 0 abajo
            $table->foreignId('created_by')->constrained('users')
                ->restrictOnDelete(); // ROL-01 que fijó la meta
            $table->timestamps();
        });

        DB::statement('ALTER TABLE business_goals ADD CONSTRAINT chk_goal_target_positive
            CHECK (target_value > 0)');
        DB::statement('ALTER TABLE business_goals ADD CONSTRAINT chk_goal_period
            CHECK (period_end >= period_start)');
        DB::statement("
            ALTER TABLE business_goals
                ADD COLUMN branch_key BIGINT UNSIGNED GENERATED ALWAYS AS (COALESCE(branch_id, 0)) VIRTUAL,
                ADD UNIQUE KEY uniq_business_goal (business_id, branch_key, kpi_code, period_start)
        ");            
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('business_goals');
    }
};
