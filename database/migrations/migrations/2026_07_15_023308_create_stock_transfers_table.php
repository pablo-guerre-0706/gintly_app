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
        Schema::create('stock_transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();

            // Dos FK a tabla warehouses: tabla explícita obligatoria (convención inferiría
            // 'from_warehouses' / 'to_warehouses', que no existen)
            $table->foreignId('from_warehouse_id')->constrained('warehouses')
                ->restrictOnDelete();
            $table->foreignId('to_warehouse_id')->constrained('warehouses')
                ->restrictOnDelete();

            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->string('code', 30);
            $table->enum('status', ['pendiente', 'completado', 'cancelado'])->index();
            $table->timestamp('transferred_at');
            $table->string('notes', 500)->nullable();
            $table->timestamps();
            $table->unique(['business_id', 'code']);
        });

        // Regla de dominio: para no traspasar a la misma bodega
        DB::statement('ALTER TABLE stock_transfers ADD CONSTRAINT chk_transfer_diff_warehouse
            CHECK (from_warehouse_id <> to_warehouse_id)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_transfers');
    }
};
