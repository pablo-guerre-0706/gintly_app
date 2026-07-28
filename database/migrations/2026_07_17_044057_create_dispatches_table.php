<?php

declare(strict_types=1);

use App\Enums\DispatchStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('dispatches')) {
            return; // Idempotencia.
        }

        Schema::create('dispatches', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();
            $table->foreignId('invoice_id')->constrained('invoices')->restrictOnDelete();
            $table->foreignId('warehouse_id')->constrained('warehouses')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete(); // Responsable.

            $table->string('code', 30); // Folio D- (SequenceGenerator). Generado por el servidor.

            $table->enum('status', DispatchStatus::values())
                ->default(DispatchStatus::Registrado->value); // Nunca nace 'revertido'.

            $table->string('received_by', 160)->nullable(); // Receptor declarado.
            $table->timestamp('dispatched_at');

            // Reversión (RF-09-04): metadatos obligatorios cuando status='revertido'.
            $table->foreignId('reverted_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('reverted_at')->nullable();
            $table->string('revert_reason', 255)->nullable();

            $table->string('notes', 500)->nullable();
            $table->timestamps();

            // Folio único por negocio (business_id leftmost respalda la FK: sin riesgo 1553).
            $table->unique(['business_id', 'code'], 'uniq_dispatch_code');

            $table->index('status', 'idx_dispatch_status');
            $table->index('invoice_id', 'idx_dispatch_invoice');
            $table->index('warehouse_id', 'idx_dispatch_warehouse');
        });

        // Coherencia de reversión: si está revertido, exige responsable Y marca temporal.
        DB::statement(
            'ALTER TABLE `dispatches` ADD CONSTRAINT `chk_dispatch_revert_coherence` '
            . "CHECK (`status` <> 'revertido' OR (`reverted_by` IS NOT NULL AND `reverted_at` IS NOT NULL))"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('dispatches');
    }
};
