<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MOD-08 · Enlace fiscal del abono (RF-07 + RF-08).
 *
 * Todo abono a una CxC debe quedar reflejado en el libro fiscal de la factura
 * (invoice_payments), además de su registro trazable en receivable_payments. Este FK
 * relaciona 1:1 ambos asientos, creados atómicamente por ReceivableService::abonar,
 * de modo que NO existan dos registros monetarios independientes que puedan contarse
 * dos veces: la fuente fiscal es invoice_payments; la fuente del historial de abonos
 * de la CxC es receivable_payments, enlazada a su pago fiscal.
 *
 * Nullable: las filas históricas (previas a la función) no tienen enlace. RESTRICT:
 * el pago fiscal no se borra mientras exista el abono que lo referencia.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('receivable_payments', 'invoice_payment_id')) {
            return;
        }

        Schema::table('receivable_payments', function (Blueprint $table): void {
            $table->foreignId('invoice_payment_id')
                ->nullable()
                ->after('cash_session_id')
                ->constrained('invoice_payments')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('receivable_payments', function (Blueprint $table): void {
            $table->dropForeign(['invoice_payment_id']);
            $table->dropColumn('invoice_payment_id');
        });
    }
};
