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
        Schema::create('invoice_sale', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();
            // RESTRICT hacia sales: anular una factura no borra el pedido (el evento comercial ocurrió)
            $table->foreignId('sale_id')->constrained('sales')->restrictOnDelete();
            $table->unique(['invoice_id', 'sale_id']); // un pedido no se enlaza dos veces a la misma factura
            // Puente puro: sin timestamps
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoice_sale');
    }
};
