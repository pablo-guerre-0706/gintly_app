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
        Schema::create('goods_receipt_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->foreignId('goods_receipt_id')->constrained('goods_receipts')
                ->cascadeOnDelete(); // la línea muere con su recibo
            $table->foreignId('purchase_order_item_id')->constrained('purchase_order_items')->restrictOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->decimal('received_quantity', 14, 3);
            $table->decimal('invoiced_unit_cost', 14, 4);   // costo facturado (pilar del match vs. agreed_unit_cost)
            $table->decimal('line_total', 14, 2);
            $table->unique(
                ['goods_receipt_id', 'purchase_order_item_id'], 
                'goods_receipt_order_item_unique'
            );
        });

        DB::statement('ALTER TABLE goods_receipt_items ADD CONSTRAINT chk_gri_received_positive
            CHECK (received_quantity > 0)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('goods_receipt_items');
    }
};
