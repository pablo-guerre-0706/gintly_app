<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // cash_movements.sale_id → sales
        // RESTRICT, no borras una venta que dejó rastro en el libro de caja (preserva la traza)
        Schema::table('cash_movements', function (Blueprint $table) {
            $table->foreign('sale_id')
                  ->references('id')->on('sales')
                  ->restrictOnDelete();
        });

        // RESTRICT por coherencia con los otros orígenes del kardex (stock_transfer_id,
        // inventory_adjustment_id): un movimiento de inventario nunca queda huérfano.
        Schema::table('inventory_movements', function (Blueprint $table) {
            $table->foreign('purchase_order_id')
                  ->references('id')->on('purchase_orders')
                  ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        // Reversible: se sueltan las FK sin tocar las columnas (que siguen existiendo).
        Schema::table('cash_movements', function (Blueprint $table) {
            $table->dropForeign(['sale_id']);
        });

        Schema::table('inventory_movements', function (Blueprint $table) {
            $table->dropForeign(['purchase_order_id']);
        });
    }
};