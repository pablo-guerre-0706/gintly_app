<?php

declare(strict_types=1);

use App\Enums\ReconciliationRunType;
use App\Enums\ReconciliationScope;
use App\Enums\ReconciliationStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('reconciliation_runs')) {
            return;
        }

        Schema::create('reconciliation_runs', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->restrictOnDelete();
            $table->foreignId('triggered_by')->nullable()->constrained('users')->restrictOnDelete();

            $table->enum('run_type', ReconciliationRunType::values())->index('idx_recon_run_type');
            $table->enum('scope', ReconciliationScope::values())->index('idx_recon_scope');
            $table->enum('status', ReconciliationStatus::values())->index('idx_recon_status');

            $table->unsignedSmallInteger('anomalies_found')->default(0);
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();

            $table->timestamp('created_at')->nullable(); // Solo created_at (diccionario).
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reconciliation_runs');
    }
};
