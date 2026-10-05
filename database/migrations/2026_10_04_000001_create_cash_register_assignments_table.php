<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * MOD-06 · Asignación administrativa Caja–Cajero con HISTORIAL TEMPORAL. ADITIVA e IDEMPOTENTE.
 *
 * Una asignación ACTIVA (ended_at IS NULL) vincula una caja a un cajero (ROL-03 con perfil cajero) de la
 * MISMA sucursal. Invariantes de MOTOR (columnas generadas VIRTUALES + UNIQUE), no solo de aplicación:
 *   · como máximo UN cajero activo por caja  (active_register_lock)
 *   · como máximo UNA caja activa por cajero  (active_user_lock)
 * Reasignar = finalizar (ended_at/ended_by) la activa y crear otra: el historial es append-only, nunca se
 * modifica salvo el cierre único de la vigencia, ni se borra. Las sesiones de caja conservan su propio
 * opened_by/cash_register, de modo que el historial de sesiones es independiente de estas asignaciones.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('cash_register_assignments')) {
            return;
        }

        Schema::create('cash_register_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            // Sucursal denormalizada: caja y cajero comparten branch; sirve de índice y refuerzo de integridad.
            $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();
            $table->foreignId('cash_register_id')->constrained('cash_registers')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();        // cajero
            $table->foreignId('assigned_by')->constrained('users')->restrictOnDelete();     // ROL-01/ROL-02
            $table->timestamp('assigned_at');
            $table->timestamp('ended_at')->nullable();
            $table->foreignId('ended_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['business_id', 'cash_register_id', 'ended_at'], 'idx_cra_register');
            $table->index(['business_id', 'user_id', 'ended_at'], 'idx_cra_user');
        });

        // Candados de MOTOR: una activa por caja y una activa por cajero (los NULL no colisionan).
        DB::statement("
            ALTER TABLE cash_register_assignments
                ADD COLUMN active_register_lock BIGINT UNSIGNED
                    GENERATED ALWAYS AS (CASE WHEN ended_at IS NULL THEN cash_register_id END) VIRTUAL,
                ADD UNIQUE KEY uniq_active_register_assignment (active_register_lock)
        ");
        DB::statement("
            ALTER TABLE cash_register_assignments
                ADD COLUMN active_user_lock BIGINT UNSIGNED
                    GENERATED ALWAYS AS (CASE WHEN ended_at IS NULL THEN user_id END) VIRTUAL,
                ADD UNIQUE KEY uniq_active_user_assignment (active_user_lock)
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_register_assignments');
    }
};
