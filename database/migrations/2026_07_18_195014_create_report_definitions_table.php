<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('report_definitions')) {
            return;
        }

        Schema::create('report_definitions', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();

            $table->string('name', 120);
            $table->string('report_type', 50)->index('idx_report_def_type');
            $table->json('filters')->nullable();
            $table->boolean('is_scheduled')->default(false); // Motor de envío: Fase 2.
            $table->string('schedule_cron', 50)->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_definitions');
    }
};
