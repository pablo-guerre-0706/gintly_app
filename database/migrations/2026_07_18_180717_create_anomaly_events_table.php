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
        Schema::create('anomaly_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->foreignId('anomaly_id')->constrained('anomalies')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->restrictOnDelete(); // NULL si fue el sistema
            // VARCHAR, no ENUM: la bitácora registra el vocabulario de estados sin acoplarse a él
            $table->string('from_status', 20)->nullable(); // NULL en la detección inicial
            $table->string('to_status', 20);
            $table->string('comment', 500)->nullable(); // justificación/observación del paso
            $table->timestamp('changed_at')->index();
            $table->timestamp('created_at')->nullable(); // INSERT-only: inmutable
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('anomaly_events');
    }
};
