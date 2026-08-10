<?php

declare(strict_types=1);

use App\Enums\AnomalyRuleCode;
use App\Enums\AnomalySeverity;
use App\Enums\AnomalyThresholdType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('anomaly_rules')) {
            return;
        }

        Schema::create('anomaly_rules', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();

            $table->enum('code', AnomalyRuleCode::values());
            $table->string('name', 120);
            $table->decimal('threshold_value', 14, 2)->nullable(); // bcmath. NULL = sin umbral.
            $table->enum('threshold_type', AnomalyThresholdType::values());
            $table->enum('default_severity', AnomalySeverity::values());
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            // Catálogo cerrado: una regla por código y negocio (idempotencia del seed).
            $table->unique(['business_id', 'code'], 'uniq_anomaly_rule_code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('anomaly_rules');
    }
};
