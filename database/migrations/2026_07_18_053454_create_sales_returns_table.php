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
        Schema::create('sales_returns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();
            $table->foreignId('invoice_id')->constrained('invoices')->restrictOnDelete(); // factura original, intacta
            $table->foreignId('customer_id')->constrained('customers')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();        // quién autorizó/recibió
            $table->string('code', 30);
            $table->enum('status', ['registrada', 'procesada', 'anulada'])->default('registrada')->index();
            $table->decimal('total_returned', 14, 2); // CHECK >= 0 abajo
            $table->timestamp('returned_at');
            $table->string('notes', 500)->nullable();
            $table->timestamps();
            $table->unique(['business_id', 'code']);
        });

        DB::statement('ALTER TABLE sales_returns ADD CONSTRAINT chk_return_total
            CHECK (total_returned >= 0)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sales_returns');
    }
};
