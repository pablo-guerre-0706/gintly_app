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
        Schema::create('accounts_receivables', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('customers')->restrictOnDelete(); // deudor

            $table->foreignId('invoice_id')->constrained('invoices')->restrictOnDelete();
            // FK y UNIQUE separados: no dependo del orden del encadenamiento fluido, es 100% predecible.
            $table->unique('invoice_id');

            $table->decimal('total_amount', 14, 2);            // = invoice.total. CHECK > 0
            $table->decimal('paid_amount', 14, 2)->default(0); // suma de abonos aplicados
            $table->enum('status', ['pendiente', 'parcial', 'pagada', 'vencida'])
                ->default('pendiente')->index();
            $table->date('due_date')->nullable()->index();     // indexado: el cron de 'vencida' filtra por aquí
            $table->timestamps();
        });

        DB::statement('ALTER TABLE accounts_receivables ADD CONSTRAINT chk_ar_total_positive
            CHECK (total_amount > 0)');
        DB::statement("ALTER TABLE accounts_receivables
            ADD COLUMN balance DECIMAL(14,2)
            GENERATED ALWAYS AS (total_amount - paid_amount) STORED AFTER paid_amount");
        DB::statement('ALTER TABLE accounts_receivables ADD CONSTRAINT chk_ar_balance_non_negative
            CHECK (balance >= 0)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('accounts_receivables');
    }
};
