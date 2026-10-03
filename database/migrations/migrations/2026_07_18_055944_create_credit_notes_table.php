<?php

declare(strict_types=1);

use App\Enums\CreditNoteResolutionType;
use App\Enums\CreditNoteStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('credit_notes')) {
            return;
        }

        Schema::create('credit_notes', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->foreignId('invoice_id')->constrained('invoices')->restrictOnDelete();
            $table->foreignId('sales_return_id')->constrained('sales_returns')->restrictOnDelete();
            $table->foreignId('customer_id')->constrained('customers')->restrictOnDelete();
            $table->foreignId('cash_session_id')->nullable()->constrained('cash_sessions')->restrictOnDelete();
            $table->foreignId('issued_by')->constrained('users')->restrictOnDelete();

            $table->string('folio', 30); // Folio fiscal NC- (FolioGenerator + document_sequences).

            $table->enum('resolution_type', CreditNoteResolutionType::values())->index('idx_cn_resolution');
            $table->decimal('total_amount', 14, 2); // bcmath.
            $table->decimal('tax_amount', 14, 2)->default(0); // IVA proporcional revertido. bcmath.

            $table->enum('status', CreditNoteStatus::values())->default(CreditNoteStatus::Emitida->value)->index('idx_cn_status');
            $table->timestamp('issued_at');
            $table->timestamps();

            $table->unique(['business_id', 'folio'], 'uniq_credit_note_folio');
            // Una devolución ⇒ exactamente una NC.
            $table->unique('sales_return_id', 'uniq_credit_note_return');

            $table->index('invoice_id', 'idx_cn_invoice');
            $table->index('customer_id', 'idx_cn_customer');
        });

        DB::statement(
            'ALTER TABLE `credit_notes` ADD CONSTRAINT `chk_credit_note_total` CHECK (`total_amount` > 0)'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_notes');
    }
};
