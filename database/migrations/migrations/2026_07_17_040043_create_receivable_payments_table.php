<?php

declare(strict_types=1);

use App\Enums\PaymentMethod;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('receivable_payments')) {
            return;
        }

        Schema::create('receivable_payments', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('business_id')
                ->constrained('businesses')
                ->cascadeOnDelete();

            // OJO: la columna es 'accounts_receivable_id' (plural en la tabla, según diccionario).
            $table->foreignId('accounts_receivable_id')
                ->constrained('accounts_receivables')
                ->restrictOnDelete();

            $table->foreignId('cash_session_id')
                ->nullable()
                ->constrained('cash_sessions')
                ->restrictOnDelete();         // Obligatoria solo si el medio es efectivo (validado aparte).

            $table->foreignId('user_id')
                ->constrained('users')
                ->restrictOnDelete();         // Responsable del cobro (no-repudio).

            $table->decimal('amount', 14, 2); // bcmath escala 2.

            $table->enum('payment_method', PaymentMethod::values())
                ->index('idx_rp_payment_method');

            $table->string('reference', 100)->nullable();

            $table->timestamp('paid_at');     // Momento del abono.

            // APPEND-ONLY: solo created_at, SIN updated_at.
            $table->timestamp('created_at')->nullable();
        });

        DB::statement(
            'ALTER TABLE `receivable_payments` '
            . 'ADD CONSTRAINT `chk_rp_amount_positive` CHECK (`amount` > 0)'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('receivable_payments');
    }
};
