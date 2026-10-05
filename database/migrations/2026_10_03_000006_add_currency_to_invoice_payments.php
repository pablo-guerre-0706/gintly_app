<?php

declare(strict_types=1);

use App\Enums\Currency;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * MOD-07/06 · Doble moneda NIO/USD en los pagos de factura (pagos mixtos). ADITIVA e IDEMPOTENTE.
 *
 * La factura se denomina en NIO. Cada leg de pago conserva su moneda nativa, el importe original (`amount`),
 * la TASA snapshot congelada al emitir (`exchange_rate`) y el EQUIVALENTE en NIO con el que financia el
 * total (`base_amount` generado = amount × tasa). La suma de equivalentes NIO es la que salda el total.
 *
 * Compatibilidad histórica: los pagos previos quedan como NIO con tasa 1 (defaults) ⇒ base_amount = amount.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('invoice_payments', 'currency')) {
            return;
        }

        $base = Currency::base()->value;

        DB::statement(
            "ALTER TABLE invoice_payments
                ADD COLUMN currency CHAR(3) NOT NULL DEFAULT '{$base}' AFTER amount,
                ADD COLUMN exchange_rate DECIMAL(14,6) NOT NULL DEFAULT 1 AFTER currency"
        );

        DB::statement(
            'ALTER TABLE invoice_payments
                ADD COLUMN base_amount DECIMAL(18,2)
                    GENERATED ALWAYS AS (amount * exchange_rate) STORED AFTER exchange_rate'
        );

        DB::statement('ALTER TABLE invoice_payments ADD CONSTRAINT chk_invoice_payment_rate_positive CHECK (exchange_rate > 0)');
        DB::statement("ALTER TABLE invoice_payments ADD CONSTRAINT chk_invoice_payment_currency CHECK (currency IN ('".implode("','", Currency::values())."'))");
        DB::statement("ALTER TABLE invoice_payments ADD CONSTRAINT chk_invoice_payment_nio_rate CHECK (currency <> '{$base}' OR exchange_rate = 1)");
    }

    public function down(): void
    {
        foreach (['chk_invoice_payment_rate_positive', 'chk_invoice_payment_currency', 'chk_invoice_payment_nio_rate'] as $check) {
            try {
                DB::statement("ALTER TABLE invoice_payments DROP CHECK {$check}");
            } catch (\Throwable) {
                // best-effort
            }
        }

        foreach (['base_amount', 'exchange_rate', 'currency'] as $column) {
            if (Schema::hasColumn('invoice_payments', $column)) {
                Schema::table('invoice_payments', fn (Blueprint $table) => $table->dropColumn($column));
            }
        }
    }
};
