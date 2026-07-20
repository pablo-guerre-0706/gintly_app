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
        Schema::create('physical_counts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')
                ->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')
                ->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')
                ->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')
                ->restrictOnDelete(); // trazabilidad: quién contó

            $table->decimal('system_quantity', 14, 3);   // saldo lógico al momento del conteo
            $table->decimal('counted_quantity', 14, 3);  // lo contado físicamente

            $table->enum('status', ['abierto', 'justificado', 'ajustado'])->index();
            $table->string('notes', 500)->nullable();
            $table->timestamp('counted_at');
            $table->timestamps();
        });

        DB::statement("ALTER TABLE physical_counts
            ADD COLUMN difference DECIMAL(14,3)
            GENERATED ALWAYS AS (counted_quantity - system_quantity) STORED AFTER counted_quantity");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('physical_counts');
    }
};
