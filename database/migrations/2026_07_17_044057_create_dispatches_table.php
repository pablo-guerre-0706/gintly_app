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
        Schema::create('dispatches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();
            // invoice_id indexado por la propia FK: se cuelga del comprobante, no del pedido
            $table->foreignId('invoice_id')->constrained('invoices')->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();

            $table->string('code', 30);
            $table->enum('status', ['registrado', 'revertido'])->default('registrado')->index();
            $table->string('received_by', 160)->nullable();  // nombre de quien retira (cliente)
            $table->timestamp('dispatched_at');              // momento del retiro físico

            // Bloque de reversión: el retiro no se borra, se revierte con rastro y movimiento compensatorio
            $table->foreignId('reverted_by')->nullable()->constrained('users')->restrictOnDelete(); // ROL-02
            $table->timestamp('reverted_at')->nullable();
            $table->string('revert_reason', 255)->nullable();

            $table->string('notes', 500)->nullable();
            $table->timestamps();
            $table->unique(['business_id', 'code']); // folio del retiro, único por negocio
        });

        DB::statement("ALTER TABLE dispatches ADD CONSTRAINT chk_dispatch_revert_coherence
            CHECK (status <> 'revertido' OR (reverted_by IS NOT NULL AND reverted_at IS NOT NULL))");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dispatches');
    }
};
