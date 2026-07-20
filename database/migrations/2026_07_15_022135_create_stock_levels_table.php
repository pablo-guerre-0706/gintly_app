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
        Schema::create('stock_levels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->restrictOnDelete();

            $table->decimal('quantity', 14, 3)->default(0);
            $table->decimal('min_stock', 14, 3)->nullable();
            $table->decimal('max_stock', 14, 3)->nullable();
            $table->decimal('reserved_quantity', 14, 3)->default(0);   // comprometido, no disponible
            $table->decimal('average_cost', 14, 4)->default(0);        // costo promedio ponderado

            $table->timestamp('updated_at')->nullable();               // updated_at, por diseño

            // La restricción más importante: un saldo ÚNICO por producto/bodega.
            // Sin esto, la primera concurrencia te deja dos saldos contradictorios.
            $table->unique(['product_id', 'warehouse_id']);
        });

        DB::statement('ALTER TABLE stock_levels ADD CONSTRAINT chk_stock_qty_non_negative
            CHECK (quantity >= 0)');
        DB::statement('ALTER TABLE stock_levels ADD CONSTRAINT chk_stock_reserved_non_negative
            CHECK (reserved_quantity >= 0)');
        // Invariante anti-sobreventa: lo reservado nunca supera lo físico (available >= 0).
        DB::statement('ALTER TABLE stock_levels ADD CONSTRAINT chk_stock_available_non_negative
            CHECK (reserved_quantity <= quantity)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_levels');
    }
};
