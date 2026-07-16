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
        Schema::create('cash_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();

            // RESTRICT, el libro se preserva aunque se intente borrar la sesión
            $table->foreignId('cash_session_id')->constrained('cash_sessions')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();

            $table->enum('type', ['ingreso', 'egreso'])->index();
            $table->enum('category', ['venta', 'egreso_autorizado', 'retiro', 'ajuste', 'fondo_inicial'])->index();

            // Clave del arqueo: 'efectivo' cuenta billetes. Venta con tarjeta entra al libro
            // y no al conteo físico. El cálculo de expected_amount filtra por 'efectivo'.
            $table->enum('payment_method', ['efectivo', 'transferencia', 'tarjeta'])->index();
            // siempre positivo; el signo lo da 'type'. CHECK > 0 abajo        
            $table->decimal('amount', 14, 2);
            // FK diferida → sales: solo columna, se cablea tras crear 'sales'.
            $table->unsignedBigInteger('sale_id')->nullable()->index();

            $table->foreignId('authorized_by')->nullable()->constrained('users')
                ->restrictOnDelete(); // ROL-02
            $table->string('description', 255)->nullable();
            $table->index(['cash_session_id', 'payment_method'], 'idx_movements_session_payment');
            // INSERT-only, sin updated_at. Un error no se edita, se reversa con un movimiento inverso.
            $table->timestamp('created_at')->nullable()->index();
        });
        DB::statement('ALTER TABLE cash_movements ADD CONSTRAINT chk_cash_movement_amount
            CHECK (amount > 0)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cash_movements');
    }
};
