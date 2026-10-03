<?php

declare(strict_types=1);

use App\Enums\PeriodType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('kpi_snapshots')) {
            return;
        }

        Schema::create('kpi_snapshots', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->restrictOnDelete();

            $table->string('kpi_code', 50); // Registro canónico en config/kpis.php.
            $table->enum('period_type', PeriodType::values());
            $table->date('period_start');
            $table->date('period_end');

            $table->decimal('value', 16, 4);                 // bcmath.
            $table->decimal('target_value', 16, 2)->nullable(); // Meta congelada al calcular. bcmath.
            $table->decimal('achievement_pct', 7, 2)->nullable(); // value / target × 100. bcmath.
            $table->json('metadata')->nullable();
            $table->timestamp('calculated_at');

            $table->unsignedBigInteger('branch_key')->virtualAs('COALESCE(branch_id, 0)');

            $table->timestamp('created_at')->nullable(); // Caché: solo created_at.

            // Idempotencia: una instantánea por (negocio, sucursal, KPI, período, inicio).
            $table->unique(
                ['business_id', 'branch_key', 'kpi_code', 'period_type', 'period_start'],
                'uniq_kpi_snapshot'
            );
            $table->index(['kpi_code', 'period_type', 'period_start'], 'idx_snapshot_lookup');
        });

        DB::statement('ALTER TABLE `kpi_snapshots` ADD CONSTRAINT `chk_snapshot_period` CHECK (`period_end` >= `period_start`)');
    }

    public function down(): void
    {
        Schema::dropIfExists('kpi_snapshots');
    }
};
