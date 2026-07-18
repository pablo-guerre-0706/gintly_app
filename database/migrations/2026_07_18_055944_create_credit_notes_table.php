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
        Schema::create('credit_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->foreignId('invoice_id')->constrained('invoices')->restrictOnDelete(); // factura compensada

            // FK + UNIQUE separados (patrón fiable, sin depender del orden del encadenamiento).
            // Una devolución una nota de crédito.
            $table->foreignId('sales_return_id')->constrained('sales_returns')->restrictOnDelete();
            $table->unique('sales_return_id');

            $table->foreignId('customer_id')->constrained('customers')->restrictOnDelete();
            $table->foreignId('cash_session_id')->nullable()->constrained('cash_sessions')
                ->restrictOnDelete(); // si es reembolso efectivo
            $table->foreignId('issued_by')->constrained('users')->restrictOnDelete(); // ROL-01/02

            $table->string('folio', 30); // serie secuencial fiscal propia, inmutable
            $table->enum('resolution_type', ['reembolso_efectivo', 'nota_credito_saldo', 'reduccion_cxc'])->index();
            $table->decimal('total_amount', 14, 2);          // CHECK > 0 abajo
            $table->decimal('tax_amount', 14, 2)->default(0); // IVA proporcional revertido
            $table->enum('status', ['emitida', 'anulada'])->default('emitida')->index(); // inmutable
            $table->timestamp('issued_at');
            $table->timestamps();
            $table->unique(['business_id', 'folio']);
        });

        DB::statement('ALTER TABLE credit_notes ADD CONSTRAINT chk_credit_note_total
            CHECK (total_amount > 0)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('credit_notes');
    }
};
