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
        Schema::create('accounts_payable', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained('suppliers')->restrictOnDelete();
            $table->foreignId('purchase_order_id')->constrained('purchase_orders')
                ->restrictOnDelete();
            // Recepción que origina la deuda (opcional). RESTRICT
            $table->foreignId('goods_receipt_id')->nullable()->constrained('goods_receipts')
                ->restrictOnDelete();

            $table->decimal('total_amount', 14, 2);
            $table->decimal('paid_amount', 14, 2)->default(0);
            // 'congelada' bloquea el pago cuando el 3-Way Match falla
            $table->enum('status', ['pendiente', 'congelada', 'parcial', 'pagada'])->default('pendiente')->index();
            $table->date('due_date')->nullable();

            // ROL-01 que descongeló. RESTRICT, preserva la firma de quien autorizó
            $table->foreignId('unblocked_by')->nullable()->constrained('users')
                ->restrictOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('accounts_payable');
    }
};
