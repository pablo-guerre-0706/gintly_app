<?php

declare(strict_types=1);

use App\Enums\Currency;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * MOD-06 · Doble moneda NIO/USD en los movimientos de caja (snapshot por operación).
 *
 * ADITIVA e IDEMPOTENTE. Cada movimiento conserva su moneda nativa, el importe original (columna `amount`
 * existente), la TASA snapshot congelada en el instante de la operación (`exchange_rate`, NIO por 1 unidad
 * de la moneda) y el EQUIVALENTE en moneda base (`base_amount`, columna generada STORED = amount × tasa).
 *
 * Compatibilidad histórica: los movimientos previos quedan como NIO con tasa 1 (defaults), de modo que
 * base_amount = amount y nada se reinterpreta. Invariante de motor: NIO exige tasa exactamente 1.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('cash_movements', 'currency')) {
            return;
        }

        $base = Currency::base()->value;

        // Moneda nativa + tasa snapshot. Defaults NIO/1 ⇒ históricos migran como NIO tasa 1.
        DB::statement(
            "ALTER TABLE cash_movements
                ADD COLUMN currency CHAR(3) NOT NULL DEFAULT '{$base}' AFTER amount,
                ADD COLUMN exchange_rate DECIMAL(14,6) NOT NULL DEFAULT 1 AFTER currency"
        );

        // Equivalente en moneda base, congelado por operación (informativo para el consolidado).
        DB::statement(
            'ALTER TABLE cash_movements
                ADD COLUMN base_amount DECIMAL(18,2)
                    GENERATED ALWAYS AS (amount * exchange_rate) STORED AFTER exchange_rate'
        );

        DB::statement('ALTER TABLE cash_movements ADD CONSTRAINT chk_cash_movement_rate_positive CHECK (exchange_rate > 0)');
        DB::statement("ALTER TABLE cash_movements ADD CONSTRAINT chk_cash_movement_currency CHECK (currency IN ('".implode("','", Currency::values())."'))");
        // NIO es la moneda base: su tasa es 1 por definición (no admite otra).
        DB::statement("ALTER TABLE cash_movements ADD CONSTRAINT chk_cash_movement_nio_rate CHECK (currency <> '{$base}' OR exchange_rate = 1)");

        DB::statement('ALTER TABLE cash_movements ADD INDEX idx_movements_session_currency (cash_session_id, currency)');
    }

    public function down(): void
    {
        foreach (['chk_cash_movement_rate_positive', 'chk_cash_movement_currency', 'chk_cash_movement_nio_rate'] as $check) {
            try {
                DB::statement("ALTER TABLE cash_movements DROP CHECK {$check}");
            } catch (\Throwable) {
                // best-effort
            }
        }

        try {
            DB::statement('ALTER TABLE cash_movements DROP INDEX idx_movements_session_currency');
        } catch (\Throwable) {
            // best-effort
        }

        foreach (['base_amount', 'exchange_rate', 'currency'] as $column) {
            if (Schema::hasColumn('cash_movements', $column)) {
                Schema::table('cash_movements', fn (Blueprint $table) => $table->dropColumn($column));
            }
        }
    }
};
