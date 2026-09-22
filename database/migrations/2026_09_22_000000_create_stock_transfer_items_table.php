<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Reconciliación MOD-03 (opción A): las líneas del traspaso se persisten al crear.
 *
 * Antes StockTransferService::crear() descartaba los ítems y completar() los
 * volvía a exigir por request. Ahora un traspaso 'pendiente' conserva sus líneas
 * y la confirmación las consume desde la base. Idempotente (hasTable).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('stock_transfer_items')) {
            return;
        }

        Schema::create('stock_transfer_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();

            // La línea vive y muere con su traspaso.
            $table->foreignId('stock_transfer_id')->constrained('stock_transfers')->cascadeOnDelete();

            // RESTRICT: no se borra un producto con líneas de traspaso.
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();

            $table->decimal('quantity', 14, 3);

            // Un producto no se repite en el mismo traspaso (coincide con la regla distinct del request).
            $table->unique(['stock_transfer_id', 'product_id']);
        });

        // Cantidad estrictamente positiva (paridad con inventory_movements/product_recipes).
        DB::statement('ALTER TABLE stock_transfer_items ADD CONSTRAINT chk_transfer_item_qty CHECK (quantity > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_transfer_items');
    }
};
