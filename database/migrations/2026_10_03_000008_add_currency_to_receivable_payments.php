<?php

declare(strict_types=1);

use App\Enums\Currency;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * MOD-08/06 · Doble moneda NIO/USD en los abonos de CxC. ADITIVA e IDEMPOTENTE.
 *
 * La cuenta por cobrar se denomina en NIO (igual que la factura). Cada abono conserva su moneda nativa,
 * el importe original (`amount`), la TASA snapshot congelada al cobrar (`exchange_rate`) y el EQUIVALENTE
 * en NIO (`base_amount` generado = amount × tasa) con el que amortiza el saldo. La invariante de cartera
 * pasa a expresarse en el equivalente NIO: Σ base_amount == accounts_receivable.paid_amount.
 *
 * Compatibilidad histórica: los abonos previos quedan como NIO tasa 1 (defaults) ⇒ base_amount = amount.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('receivable_payments', 'currency')) {
            return;
        }

        $base = Currency::base()->value;

        DB::statement(
            "ALTER TABLE receivable_payments
                ADD COLUMN currency CHAR(3) NOT NULL DEFAULT '{$base}' AFTER amount,
                ADD COLUMN exchange_rate DECIMAL(14,6) NOT NULL DEFAULT 1 AFTER currency"
        );

        DB::statement(
            'ALTER TABLE receivable_payments
                ADD COLUMN base_amount DECIMAL(18,2)
                    GENERATED ALWAYS AS (amount * exchange_rate) STORED AFTER exchange_rate'
        );

        DB::statement('ALTER TABLE receivable_payments ADD CONSTRAINT chk_rp_rate_positive CHECK (exchange_rate > 0)');
        DB::statement("ALTER TABLE receivable_payments ADD CONSTRAINT chk_rp_currency CHECK (currency IN ('".implode("','", Currency::values())."'))");
        DB::statement("ALTER TABLE receivable_payments ADD CONSTRAINT chk_rp_nio_rate CHECK (currency <> '{$base}' OR exchange_rate = 1)");
    }

    public function down(): void
    {
        foreach (['chk_rp_rate_positive', 'chk_rp_currency', 'chk_rp_nio_rate'] as $check) {
            try {
                DB::statement("ALTER TABLE receivable_payments DROP CHECK {$check}");
            } catch (\Throwable) {
                // best-effort
            }
        }

        foreach (['base_amount', 'exchange_rate', 'currency'] as $column) {
            if (Schema::hasColumn('receivable_payments', $column)) {
                Schema::table('receivable_payments', fn (Blueprint $table) => $table->dropColumn($column));
            }
        }
    }
};
