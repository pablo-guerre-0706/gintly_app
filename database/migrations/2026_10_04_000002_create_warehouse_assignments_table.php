<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * MOD-03 · Asignación Bodega–Bodeguero (muchos-a-muchos con HISTORIAL TEMPORAL). ADITIVA e IDEMPOTENTE.
 *
 * Una asignación ACTIVA (ended_at IS NULL) habilita a un bodeguero (ROL-03 con perfil bodeguero) a operar
 * una bodega de SU sucursal. Relación M:N: un bodeguero puede tener varias bodegas activas y una bodega
 * varios bodegueros activos. Invariante de MOTOR: no se repite el par (bodega, usuario) activo — columna
 * generada VIRTUAL `active_pair_lock` + UNIQUE. Finalizar fija ended_at/ended_by; el historial es
 * append-only (nunca se modifica salvo el cierre único de la vigencia, ni se borra).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('warehouse_assignments')) {
            return;
        }

        Schema::create('warehouse_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();        // bodeguero
            $table->foreignId('assigned_by')->constrained('users')->restrictOnDelete();     // ROL-01/ROL-02
            $table->timestamp('assigned_at');
            $table->timestamp('ended_at')->nullable();
            $table->foreignId('ended_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['business_id', 'warehouse_id', 'ended_at'], 'idx_wa_warehouse');
            $table->index(['business_id', 'user_id', 'ended_at'], 'idx_wa_user');
        });

        // Candado de MOTOR: a lo sumo UNA asignación activa por par (bodega, usuario). Los NULL no colisionan.
        DB::statement("
            ALTER TABLE warehouse_assignments
                ADD COLUMN active_pair_lock VARCHAR(48)
                    GENERATED ALWAYS AS (CASE WHEN ended_at IS NULL THEN CONCAT(warehouse_id, '-', user_id) END) VIRTUAL,
                ADD UNIQUE KEY uniq_active_warehouse_assignment (active_pair_lock)
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('warehouse_assignments');
    }
};
