<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * MOD-07 · Infraestructura fiscal normalizada.
 *
 * Regla fiscal configurable, persistida y perteneciente al negocio. Resuelve la
 * tasa por clase fiscal (tax_class) con ámbito general del negocio (branch_id NULL)
 * o específico por sucursal/jurisdicción (branch_id). Las tasas se guardan como
 * fracción decimal(8,6): 0.150000 = 15 %, 0.000000 = tasa cero.
 *
 * Versionado sin pérdida de trazabilidad: las reglas no se borran físicamente; se
 * desactivan (is_active=false) y quedan en la tabla. Una columna generada VIRTUAL
 * + UNIQUE garantiza como máximo UNA regla ACTIVA por (clase, ámbito), emulando el
 * índice parcial que MySQL 8 no tiene (mismo patrón que las sesiones de caja).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tax_rules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->enum('tax_class', ['standard', 'reduced', 'zero_rated', 'exempt'])->index();
            // NULL = regla general del negocio; con valor = regla específica de sucursal.
            $table->foreignId('branch_id')->nullable()->constrained('branches')->restrictOnDelete();
            $table->decimal('rate', 8, 6);                 // fracción: 0.150000 = 15 %
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['business_id', 'tax_class', 'branch_id']);
        });

        // La tasa es una fracción válida en [0, 1].
        DB::statement('ALTER TABLE tax_rules ADD CONSTRAINT chk_tax_rule_rate
            CHECK (rate >= 0 AND rate <= 1)');

        // Candado de motor: a lo sumo UNA regla activa por (negocio, clase, ámbito).
        // active_scope_lock vale <clase>:<branch_id|0> solo mientras is_active=1, y
        // NULL en reglas inactivas (los NULL no colisionan → histórico ilimitado).
        DB::statement("
            ALTER TABLE tax_rules
                ADD COLUMN active_scope_lock VARCHAR(64)
                    GENERATED ALWAYS AS (
                        CASE WHEN is_active = 1
                             THEN CONCAT(business_id, ':', tax_class, ':', COALESCE(branch_id, 0))
                        END
                    ) VIRTUAL,
                ADD UNIQUE KEY uniq_active_tax_rule_scope (active_scope_lock)
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('tax_rules');
    }
};
