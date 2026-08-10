<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('anomaly_events')) {
            return;
        }

        Schema::create('anomaly_events', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->foreignId('anomaly_id')->constrained('anomalies')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->restrictOnDelete();

            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20);
            $table->string('comment', 500)->nullable();
            $table->timestamp('changed_at')->index('idx_anomaly_event_changed');

            $table->timestamp('created_at')->nullable(); // Append-only: solo created_at.
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('anomaly_events');
    }
};
