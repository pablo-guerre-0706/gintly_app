<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('dispatch_items')) {
            return;
        }

        Schema::create('dispatch_items', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->foreignId('dispatch_id')->constrained('dispatches')->cascadeOnDelete();
            $table->foreignId('sale_item_id')->constrained('sale_items')->restrictOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();

            $table->decimal('quantity', 14, 3); // Cantidad entregada de la línea. bcmath.

            // Sin timestamps (diccionario).
            $table->index('dispatch_id', 'idx_dispatch_item_dispatch');
            $table->index('sale_item_id', 'idx_dispatch_item_sale_item');
        });

        DB::statement(
            'ALTER TABLE `dispatch_items` ADD CONSTRAINT `chk_dispatch_item_quantity` '
            . 'CHECK (`quantity` > 0)'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('dispatch_items');
    }
};
