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
        Schema::create('inventory_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->restrictOnDelete(); // NULL si ROL-SYS

            $table->enum('type', ['entrada', 'salida', 'ajuste', 'traspaso'])->index();
            $table->decimal('quantity', 14, 3);        // siempre positiva; el type define el signo
            $table->decimal('balance_after', 14, 3);   // foto histórica del saldo resultante
            $table->decimal('unit_cost', 14, 4)->nullable();

            // FK NULLABLES (FK inmediatas y FK diferidas)
            $table->foreignId('stock_transfer_id')->nullable()
                ->constrained('stock_transfers')->restrictOnDelete();
            $table->foreignId('inventory_adjustment_id')->nullable()
                ->constrained('inventory_adjustments')->restrictOnDelete();
            // unsignedBigInteger crea el tipo idéntico (BIGINT UNSIGNED), espera la FK diferida.
            $table->unsignedBigInteger('purchase_order_id')->nullable()->index();
            $table->unsignedBigInteger('dispatch_id')->nullable()->index();

            $table->string('reason', 255)->nullable();
            $table->timestamp('created_at')->nullable()->index(); // kardex inmutable, sin updated_at
        });

        // Cantidad estrictamente positiva
        DB::statement('ALTER TABLE inventory_movements ADD CONSTRAINT chk_movement_quantity
            CHECK (quantity > 0)');

        // Exclusividad de origen: como máximo UNA FK de origen poblada (0 permitido para manuales).
        // Las booleanas suman 1/0 en MySQL 8; el diseño defensivo que reemplaza al morph con rigor.
        DB::statement('ALTER TABLE inventory_movements ADD CONSTRAINT chk_movement_single_origin
            CHECK (
            (purchase_order_id IS NOT NULL) +
            (dispatch_id IS NOT NULL) +
            (stock_transfer_id IS NOT NULL) +
            (inventory_adjustment_id IS NOT NULL) <= 1
        )');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_movements');
    }
};
