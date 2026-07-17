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
        Schema::create('receivable_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();

            $table->foreignId('accounts_receivable_id')
                ->constrained('accounts_receivables')->restrictOnDelete();

            $table->foreignId('cash_session_id')->nullable()->constrained('cash_sessions')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete(); // quién recibió el abono
            $table->decimal('amount', 14, 2);  // CHECK > 0 abajo
            $table->enum('payment_method', ['efectivo', 'transferencia', 'tarjeta'])->index();
            $table->string('reference', 100)->nullable();
            $table->timestamp('paid_at');
            $table->timestamp('created_at')->nullable(); // INSERT-only: sin updated_at
        });

        DB::statement('ALTER TABLE receivable_payments ADD CONSTRAINT chk_rp_amount_positive
            CHECK (amount > 0)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('receivable_payments');
    }
};
