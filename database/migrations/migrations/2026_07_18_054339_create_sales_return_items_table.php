<?php

declare(strict_types=1);

use App\Enums\ReturnDestination;
use App\Enums\ReturnReasonCode;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('sales_return_items')) {
            return;
        }

        Schema::create('sales_return_items', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->foreignId('sales_return_id')->constrained('sales_returns')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->foreignId('sale_item_id')->nullable()->constrained('sale_items')->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->restrictOnDelete();

            $table->decimal('quantity', 14, 3);   // bcmath.
            $table->decimal('unit_price', 14, 2);  // Precio CONGELADO de la factura. bcmath.

            $table->enum('destination', ReturnDestination::values())->index('idx_return_item_destination');
            $table->enum('reason_code', ReturnReasonCode::values());

            $table->decimal('line_total', 14, 2);  // cantidad × precio. bcmath.

            // Sin timestamps (diccionario).
            $table->index('sales_return_id', 'idx_return_item_return');
            $table->index('sale_item_id', 'idx_return_item_sale_item');
        });

        DB::statement('ALTER TABLE `sales_return_items` ADD CONSTRAINT `chk_return_item_quantity` CHECK (`quantity` > 0)');
        DB::statement('ALTER TABLE `sales_return_items` ADD CONSTRAINT `chk_return_item_price_non_negative` CHECK (`unit_price` >= 0)');
        DB::statement('ALTER TABLE `sales_return_items` ADD CONSTRAINT `chk_return_item_line_total_non_negative` CHECK (`line_total` >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_return_items');
    }
};
