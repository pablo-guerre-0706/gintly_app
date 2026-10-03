<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reconciliación MOD-04.
 *
 * `goods_receipt_items.matched` (boolean NN, def. true — Diccionario de Datos §goods_receipt_items):
 * el modelo, GoodsReceiptService (escribe `matched` por línea), la evidencia del 3-Way Match y
 * GoodsReceiptItemResource ya la usan, pero ninguna migración la creaba. En una base migrada la
 * columna no existía y TODA recepción (POST /goods-receipts) fallaba con "Unknown column 'matched'".
 * Se añade de forma idempotente para no chocar con entornos que ya la tuvieran fuera de migración.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('goods_receipt_items', 'matched')) {
            Schema::table('goods_receipt_items', function (Blueprint $table): void {
                $table->boolean('matched')->default(true)->after('line_total');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('goods_receipt_items', 'matched')) {
            Schema::table('goods_receipt_items', function (Blueprint $table): void {
                $table->dropColumn('matched');
            });
        }
    }
};
