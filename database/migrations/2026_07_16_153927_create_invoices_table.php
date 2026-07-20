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
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();
            $table->foreignId('customer_id')->constrained('customers')->restrictOnDelete();
            $table->foreignId('cash_session_id')->nullable()->constrained('cash_sessions')
                ->restrictOnDelete();
            $table->foreignId('issued_by')->constrained('users')->restrictOnDelete();

            $table->string('folio', 30);   // secuencial fiscal, único, inmutable
            $table->enum('payment_type', ['contado', 'credito'])->default('contado')->index(); // define si va a CxC
            $table->enum('payment_status', ['pagada', 'parcial', 'pendiente'])->index();
            $table->enum('status', ['emitida', 'anulada'])->default('emitida')->index();        // NUNCA se borra

            $table->decimal('subtotal', 14, 2);
            $table->decimal('tax_amount', 14, 2)->default(0);      // IVA parametrizable por negocio (no 0.15 fijo)
            $table->decimal('discount_amount', 14, 2)->default(0);
            $table->decimal('total', 14, 2);                       // CHECK >= 0 abajo
            $table->decimal('paid_amount', 14, 2)->default(0);

            $table->foreignId('voided_by')->nullable()->constrained('users')->restrictOnDelete(); // ROL-01 que anuló
            $table->timestamp('voided_at')->nullable();
            $table->string('void_reason', 255)->nullable();

            $table->timestamp('issued_at');
            $table->timestamps();

            // El folio único por negocio: esta línea es lo que hace el folio "sagrado" a nivel motor
            $table->unique(['business_id', 'folio']);
        });

        DB::statement('ALTER TABLE invoices ADD CONSTRAINT chk_invoice_total
            CHECK (total >= 0)');
        DB::statement('ALTER TABLE invoices ADD CONSTRAINT chk_invoice_paid_non_negative
            CHECK (paid_amount >= 0)');
        DB::statement('ALTER TABLE invoices ADD CONSTRAINT chk_invoice_paid_not_exceed
            CHECK (paid_amount <= total)');
        DB::statement("ALTER TABLE invoices ADD CONSTRAINT chk_invoice_void_coherence
            CHECK (status <> 'anulada' OR voided_by IS NOT NULL)");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
