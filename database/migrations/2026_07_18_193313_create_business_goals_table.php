<?php

declare(strict_types=1);

use App\Enums\BusinessGoalKpiCode;
use App\Enums\PeriodType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('business_goals')) {
            return;
        }

        Schema::create('business_goals', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->restrictOnDelete();

            $table->enum('kpi_code', BusinessGoalKpiCode::values());
            $table->enum('period_type', PeriodType::values());
            $table->date('period_start');
            $table->date('period_end');
            $table->decimal('target_value', 16, 2); // bcmath.
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();

            // Colapsa el NULL de sucursal para permitir unicidad con meta global.
            $table->unsignedBigInteger('branch_key')->virtualAs('COALESCE(branch_id, 0)');

            $table->timestamps();

            // Una meta por (negocio, sucursal, KPI, tipo de período, inicio).
            $table->unique(
                ['business_id', 'branch_key', 'kpi_code', 'period_type', 'period_start'],
                'uniq_business_goal'
            );
            $table->index(['kpi_code', 'period_type'], 'idx_goal_kpi_period');
        });

        DB::statement('ALTER TABLE `business_goals` ADD CONSTRAINT `chk_goal_target_positive` CHECK (`target_value` > 0)');
        DB::statement('ALTER TABLE `business_goals` ADD CONSTRAINT `chk_goal_period` CHECK (`period_end` >= `period_start`)');
    }

    public function down(): void
    {
        Schema::dropIfExists('business_goals');
    }
};
