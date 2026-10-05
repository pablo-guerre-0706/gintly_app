<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * MOD-06 · Doble moneda NIO/USD en el arqueo ciego independiente. ADITIVA e IDEMPOTENTE.
 *
 * El conteo de la moneda BASE (NIO) permanece en counted_amount/expected_amount/counted_denominations/
 * difference. Esta migración añade las columnas espejo para USD, reconciliadas por separado. Compatibilidad
 * histórica: los arqueos previos quedan con columnas USD nulas (conteo NIO puro).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('cash_counts', 'counted_amount_usd')) {
            return;
        }

        Schema::table('cash_counts', function (Blueprint $table): void {
            $table->decimal('counted_amount_usd', 14, 2)->nullable()->after('counted_amount');
            $table->decimal('expected_amount_usd', 14, 2)->nullable()->after('expected_amount');
            $table->json('counted_denominations_usd')->nullable()->after('counted_denominations');
        });

        DB::statement('ALTER TABLE cash_counts ADD CONSTRAINT chk_cash_count_amounts_usd
            CHECK (counted_amount_usd IS NULL OR (counted_amount_usd >= 0 AND expected_amount_usd >= 0))');

        DB::statement('ALTER TABLE cash_counts
            ADD COLUMN difference_usd DECIMAL(14,2)
            GENERATED ALWAYS AS (counted_amount_usd - expected_amount_usd) STORED');
    }

    public function down(): void
    {
        try {
            DB::statement('ALTER TABLE cash_counts DROP CHECK chk_cash_count_amounts_usd');
        } catch (\Throwable) {
            // best-effort
        }

        if (Schema::hasColumn('cash_counts', 'difference_usd')) {
            Schema::table('cash_counts', fn (Blueprint $table) => $table->dropColumn('difference_usd'));
        }

        foreach (['counted_denominations_usd', 'expected_amount_usd', 'counted_amount_usd'] as $column) {
            if (Schema::hasColumn('cash_counts', $column)) {
                Schema::table('cash_counts', fn (Blueprint $table) => $table->dropColumn($column));
            }
        }
    }
};
