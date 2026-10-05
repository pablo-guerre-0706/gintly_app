<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * MOD-06 · Doble moneda NIO/USD en la sesión de caja. ADITIVA e IDEMPOTENTE.
 *
 * El fondo inicial, el esperado, el contado, el desglose y la diferencia de la moneda BASE (NIO) siguen
 * en las columnas existentes (opening_amount, expected_amount, counted_amount, counted_denominations,
 * difference). Esta migración añade las columnas espejo para USD, reconciliadas de forma INDEPENDIENTE:
 * una diferencia en NIO o en USD basta para marcar la sesión descuadrada.
 *
 * `session_exchange_rate` es la tasa de REFERENCIA (snapshot al cierre) con la que se expresa el saldo USD
 * en NIO para el consolidado INFORMATIVO; nunca sustituye la reconciliación por moneda. Compatibilidad
 * histórica: las sesiones previas quedan con fondo USD 0 y columnas USD nulas (operación NIO pura).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('cash_sessions', 'opening_amount_usd')) {
            return;
        }

        Schema::table('cash_sessions', function (Blueprint $table): void {
            $table->decimal('opening_amount_usd', 14, 2)->default(0)->after('opening_amount'); // fondo USD declarado
            $table->decimal('expected_amount_usd', 14, 2)->nullable()->after('expected_amount'); // teórico USD al cerrar
            $table->decimal('counted_amount_usd', 14, 2)->nullable()->after('counted_amount');    // arqueo ciego USD
            $table->json('counted_denominations_usd')->nullable()->after('counted_denominations'); // evidencia USD
            $table->decimal('session_exchange_rate', 14, 6)->nullable()->after('closing_notes');  // tasa de referencia (consolidado)
        });

        // Fondo USD nunca negativo.
        DB::statement('ALTER TABLE cash_sessions ADD CONSTRAINT chk_cash_session_opening_usd CHECK (opening_amount_usd >= 0)');

        // Diferencia USD derivada por el MOTOR (counted − expected), espejo de `difference`.
        DB::statement('ALTER TABLE cash_sessions
            ADD COLUMN difference_usd DECIMAL(14,2)
            GENERATED ALWAYS AS (counted_amount_usd - expected_amount_usd) STORED');
    }

    public function down(): void
    {
        try {
            DB::statement('ALTER TABLE cash_sessions DROP CHECK chk_cash_session_opening_usd');
        } catch (\Throwable) {
            // best-effort
        }

        if (Schema::hasColumn('cash_sessions', 'difference_usd')) {
            Schema::table('cash_sessions', fn (Blueprint $table) => $table->dropColumn('difference_usd'));
        }

        foreach (['session_exchange_rate', 'counted_denominations_usd', 'counted_amount_usd', 'expected_amount_usd', 'opening_amount_usd'] as $column) {
            if (Schema::hasColumn('cash_sessions', $column)) {
                Schema::table('cash_sessions', fn (Blueprint $table) => $table->dropColumn($column));
            }
        }
    }
};
