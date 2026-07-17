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
        Schema::create('invoice_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();
            $table->foreignId('cash_session_id')->nullable()->constrained('cash_sessions')
                ->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete(); // cajero que cobró
            $table->enum('payment_method', ['efectivo', 'transferencia', 'tarjeta'])->index(); // varias filas = mixto
            $table->decimal('amount', 14, 2);              // CHECK > 0 abajo
            $table->string('reference', 100)->nullable();  // Nº aprobación/transferencia
            $table->timestamp('paid_at');
            $table->timestamp('created_at')->nullable();   // INSERT-only: sin updated_at
        });

        DB::statement('ALTER TABLE invoice_payments ADD CONSTRAINT chk_invoice_payment_amount
            CHECK (amount > 0)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoice_payments');
    }
};
