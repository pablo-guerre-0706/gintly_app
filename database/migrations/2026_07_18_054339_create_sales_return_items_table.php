<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('sales_return_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->foreignId('sales_return_id')->constrained('sales_returns')
                ->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();

            // NULLABLE a propósito: trazabilidad ideal cuando existe la línea original, tolerante cuando no
            // (venta antigua, dato incompleto). No se pierde la devolución por no poder mapear la línea.
            $table->foreignId('sale_item_id')->nullable()->constrained('sale_items')->restrictOnDelete();

            $table->foreignId('warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->decimal('quantity', 14, 3);   // CHECK > 0 abajo
            $table->decimal('unit_price', 14, 2); // precio congelado de la factura (base de la nota de crédito)

            $table->enum('destination', ['reingreso', 'merma'])->index(); // Bifurcación explícita
            $table->enum('reason_code', ['vencido', 'defecto_fabrica', 'error_despacho', 'insatisfaccion', 'otro']);
            $table->decimal('line_total', 14, 2); // quantity × unit_price
            // Sin timestamps: el diccionario no los lista
        });

        DB::statement('ALTER TABLE sales_return_items ADD CONSTRAINT chk_sales_return_item_quantity
            CHECK (quantity > 0)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sales_return_items');
    }
};
