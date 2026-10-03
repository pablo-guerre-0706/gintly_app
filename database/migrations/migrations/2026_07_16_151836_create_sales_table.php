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
        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')
                ->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained('branches')
                ->restrictOnDelete();
            $table->foreignId('customer_id')->constrained('customers')
                ->restrictOnDelete(); // genérico por defecto
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();          // mesero/facturador
            $table->string('code', 30);
            $table->enum('status', ['abierta', 'confirmada', 'facturada', 'anulada'])->default('abierta')->index();
            $table->string('table_reference', 50)->nullable();  // mesa/mostrador
            $table->decimal('subtotal', 14, 2)->default(0);     // recalculado mientras está abierta
            $table->string('notes', 500)->nullable();
            $table->timestamp('opened_at');
            $table->timestamp('confirmed_at')->nullable();      // cuándo se cerró para facturar
            $table->timestamps();
            $table->unique(['business_id', 'code']);            // comanda interna, única por negocio
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sales');
    }
};
