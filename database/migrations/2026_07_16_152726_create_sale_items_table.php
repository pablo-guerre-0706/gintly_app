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
        Schema::create('sale_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            // CASCADE: el detalle pertenece al pedido. Un pedido facturado queda protegido por el
            // RESTRICT de invoice_sale (no se puede borrar), así que el histórico nunca se pierde.
            $table->foreignId('sale_id')->constrained('sales')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->string('description', 160);      // nombre CONGELADO (foto histórica)
            $table->decimal('quantity', 14, 3);      // CHECK > 0 abajo
            $table->decimal('unit_price', 14, 2);    // precio CONGELADO al confirmar
            $table->decimal('unit_cost', 14, 4);     // costo CONGELADO (margen/BI)
            $table->decimal('discount_amount', 14, 2)->default(0);
            $table->decimal('line_total', 14, 2);    // (quantity × unit_price) − discount_amount
            // Deliberadamente SIN unique(sale_id, product_id): el mismo producto puede ir en varias
            // líneas (rondas de lo mismo en un bar). Y sin timestamps: el diccionario no los lista.
        });

        DB::statement('ALTER TABLE sale_items ADD CONSTRAINT chk_sale_item_quantity
            CHECK (quantity > 0)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sale_items');
    }
};
