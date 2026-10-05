<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * MOD-06 · Arqueo ciego INDEPENDIENTE durante una sesión ABIERTA (RF-06-04).
 *
 * Historial append-only de conteos físicos del efectivo SIN cerrar la sesión. Cada arqueo congela su
 * evidencia (denominaciones, usuario, fecha), el esperado calculado en ese instante y la diferencia
 * (columna generada). Un arqueo NO cambia el estado de la sesión y NO genera anomalía (solo el cierre
 * formal lo hace). Inmutable: INSERT-only, sin updated_at.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('cash_counts')) {
            return;
        }

        Schema::create('cash_counts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            // RESTRICT: el historial de arqueos se preserva aunque se intente borrar la sesión.
            $table->foreignId('cash_session_id')->constrained('cash_sessions')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete(); // quién arqueó (no-repudio)

            $table->decimal('counted_amount', 14, 2);   // efectivo declarado (arqueo ciego)
            $table->decimal('expected_amount', 14, 2);  // snapshot del esperado al momento del arqueo
            $table->json('counted_denominations');       // evidencia inmutable del desglose

            $table->timestamp('counted_at');
            // INSERT-only: sin updated_at. Un arqueo no se edita; se registra otro.
            $table->timestamp('created_at')->nullable()->index();

            $table->index(['business_id', 'cash_session_id'], 'idx_cash_counts_session');
        });

        DB::statement('ALTER TABLE cash_counts ADD CONSTRAINT chk_cash_count_amounts
            CHECK (counted_amount >= 0 AND expected_amount >= 0)');

        // Diferencia derivada por el MOTOR (counted − expected), coherente con cash_sessions.difference.
        DB::statement('ALTER TABLE cash_counts
            ADD COLUMN difference DECIMAL(14,2)
            GENERATED ALWAYS AS (counted_amount - expected_amount) STORED');
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_counts');
    }
};
