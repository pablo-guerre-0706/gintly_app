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
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();
            $table->foreignId('supplier_id')->constrained('suppliers')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete(); // quién generó la OC
            $table->string('code', 30);
            $table->enum('status', ['borrador', 'emitida', 'parcial', 'recibida', 'cancelada'])->index();
            $table->decimal('expected_total', 14, 2)->default(0); // suma del detalle
            $table->date('ordered_at');
            $table->string('notes', 500)->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['business_id', 'code']);
            $table->index('deleted_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_orders');
    }
};
