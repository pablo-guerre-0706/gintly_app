<?php

declare(strict_types=1);

use App\Enums\AccountReceivableStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Guarda de idempotencia: reejecutar la migración no debe fallar ni duplicar.
        if (Schema::hasTable('accounts_receivables')) {
            return;
        }

        Schema::create('accounts_receivables', function (Blueprint $table): void {
            $table->id();

            // El negocio es el dueño; si desaparece, cae su cartera.
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();

            // No se borra un cliente con CxC (respalda ERR-05B)
            $table->foreignId('customer_id')->constrained('customers')->restrictOnDelete();

            // La factura es el ancla fiscal de la deuda.
            $table->foreignId('invoice_id')->constrained('invoices')->restrictOnDelete();

            $table->decimal('total_amount', 14, 2);              // bcmath escala 2.
            $table->decimal('paid_amount', 14, 2)->default(0);   // bcmath escala 2.

            // SALDO derivado por el MOTOR (columna generada STORED). Jamás editable ni fillable.
            $table->decimal('balance', 14, 2)
                ->storedAs('total_amount - paid_amount');

            $table->enum('status', AccountReceivableStatus::values())
                ->default(AccountReceivableStatus::Pendiente->value); // Nunca nace 'vencida'.

            $table->date('due_date')->nullable();                // Emisión + 30 días (RF-08-01).

            $table->timestamps();

            // Una factura ⇒ exactamente una CxC (garantía de motor).
            $table->unique('invoice_id', 'uniq_ar_invoice');

            $table->index('status', 'idx_ar_status');
            $table->index('due_date', 'idx_ar_due_date');
            // Índice compuesto para la consulta de exposición por cliente.
            $table->index(['business_id', 'customer_id'], 'idx_ar_business_customer');
        });

        // total ≥ 0: la POSITIVIDAD AL NACER la garantiza el Service (la factura de origen es > 0).
        // '>= 0' es necesario para saldar por anulación (total := paid) sin falsear paid_amount (RF-08-07).
        DB::statement(
            'ALTER TABLE `accounts_receivables` '
            . 'ADD CONSTRAINT `chk_ar_total_positive` CHECK (`total_amount` >= 0)'
        );

        // Anti-sobre-abono ESTRUCTURAL (opción A): el saldo derivado nunca puede ser negativo.
        DB::statement(
            'ALTER TABLE `accounts_receivables` '
            . 'ADD CONSTRAINT `chk_ar_balance_non_negative` CHECK (`balance` >= 0)'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('accounts_receivables');
    }
};
