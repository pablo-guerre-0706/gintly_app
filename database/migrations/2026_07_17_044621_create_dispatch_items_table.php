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
        Schema::create('dispatch_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->foreignId('dispatch_id')->constrained('dispatches')->cascadeOnDelete();

            // RESTRICT hacia la línea facturada. Su índice (creado por la FK) es el que permite
            // SUM(quantity) por sale_item_id → cálculo de "cuánto se ha retirado de esta línea".
            $table->foreignId('sale_item_id')->constrained('sale_items')->restrictOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();

            $table->decimal('quantity', 14, 3); // cantidad retirada en este acto. CHECK > 0 abajo
            // Sin timestamps: el diccionario no los lista
        });

        DB::statement('ALTER TABLE dispatch_items ADD CONSTRAINT chk_dispatch_item_quantity
            CHECK (quantity > 0)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dispatch_items');
    }
};
