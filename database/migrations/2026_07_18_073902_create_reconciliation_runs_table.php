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
        Schema::create('reconciliation_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')
                ->restrictOnDelete(); // NULL = todas
            // NULL = la disparó el cron (ROL-SYS); con valor = disparo manual del usuario. Una tabla, dos flujos.
            $table->foreignId('triggered_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->enum('run_type', ['programada', 'manual'])->index();
            $table->enum('scope', ['caja', 'inventario_bodega', 'compras_3way', 'integral'])->index();
            $table->enum('status', ['en_proceso', 'completada', 'fallida'])->index();
            $table->unsignedSmallInteger('anomalies_found')->default(0); // SMALLINT UNSIGNED: 0–65535, de sobra
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->timestamp('created_at')->nullable(); // solo created_at (la fila muta status/finished_at)
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reconciliation_runs');
    }
};
