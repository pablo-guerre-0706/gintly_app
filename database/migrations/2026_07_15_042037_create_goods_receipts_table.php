<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('goods_receipts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();

            // RESTRICT, no borrar OC que tiene recepciones registradas (protege historial)
            $table->foreignId('purchase_order_id')->constrained('purchase_orders')
                ->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete(); // bodeguero que recibió

            $table->string('supplier_invoice_number', 60)->nullable(); // Factura por pagar
            $table->decimal('supplier_invoice_total', 14, 2)->nullable();
            $table->enum('match_status', ['ok', 'discrepancia', 'bloqueada'])->index(); // resultado del match

            $table->timestamp('received_at');
            $table->string('notes', 500)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('goods_receipts');
    }
};
