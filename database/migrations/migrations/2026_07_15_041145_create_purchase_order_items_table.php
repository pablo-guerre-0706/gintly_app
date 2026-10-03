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
        Schema::create('purchase_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();

            // ÚNICA excepción a RESTRICT en el módulo: un ítem no tiene sentido sin su OC → CASCADE
            $table->foreignId('purchase_order_id')->constrained('purchase_orders')->cascadeOnDelete();

            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->decimal('ordered_quantity', 14, 3);
            $table->decimal('received_quantity', 14, 3)->default(0); // se llena en recepción; admite parciales
            $table->decimal('agreed_unit_cost', 14, 4);              // costo pactado: pilar del 3-Way Match
            $table->decimal('line_total', 14, 2);                    // ordered_quantity × agreed_unit_cost

            $table->unique(['purchase_order_id', 'product_id']);     // un producto no se repite en la misma OC
        });

        // Para no pedir ni recibir cantidades cero o negativas
        DB::statement('ALTER TABLE purchase_order_items ADD CONSTRAINT chk_poi_ordered_qty
            CHECK (ordered_quantity > 0)');
        DB::statement('ALTER TABLE purchase_order_items ADD CONSTRAINT chk_poi_received_non_negative
            CHECK (received_quantity >= 0)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_order_items');
    }
};
