<?php

declare(strict_types=1);

use App\Enums\SalesReturnStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('sales_returns')) {
            return;
        }

        Schema::create('sales_returns', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();
            $table->foreignId('invoice_id')->constrained('invoices')->restrictOnDelete();
            $table->foreignId('customer_id')->constrained('customers')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete(); // Responsable.

            $table->string('code', 30); // Folio DV- (SequenceGenerator).

            $table->enum('status', SalesReturnStatus::values())
                ->default(SalesReturnStatus::Registrada->value);

            $table->decimal('total_returned', 14, 2)->default(0); // bcmath.
            $table->timestamp('returned_at');
            $table->string('notes', 500)->nullable();
            $table->timestamps();

            $table->unique(['business_id', 'code'], 'uniq_sales_return_code');
            $table->index('status', 'idx_sales_return_status');
            $table->index('invoice_id', 'idx_sales_return_invoice');
            $table->index('customer_id', 'idx_sales_return_customer');
        });

        DB::statement(
            'ALTER TABLE `sales_returns` ADD CONSTRAINT `chk_return_total` '
            . 'CHECK (`total_returned` >= 0)'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_returns');
    }
};
