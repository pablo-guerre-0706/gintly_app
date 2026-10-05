<?php

declare(strict_types=1);

use App\Enums\Currency;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * MOD-06 · Tipo de cambio NIO↔USD administrado por ROL-01/ROL-02 (RF-06, doble moneda).
 *
 * Historial INMUTABLE y versionado por vigencia: cada fila fija la tasa (NIO por 1 unidad de la moneda
 * extranjera) a partir de `effective_from`, con responsable (`created_by`). La tasa vigente de una moneda
 * es la fila de mayor `effective_from <= now()`. La moneda base (NIO) NO se almacena: su tasa es 1 por
 * definición. Append-only: sin updated_at; UPDATE/DELETE → ImmutableRecordException.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('exchange_rates')) {
            return;
        }

        Schema::create('exchange_rates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->char('currency', 3);                 // moneda extranjera (p. ej. USD); nunca la base.
            $table->decimal('rate', 14, 6);              // NIO por 1 unidad de `currency`. > 0.
            $table->timestamp('effective_from');         // vigencia desde.
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete(); // ROL-01/02 responsable.
            $table->timestamp('created_at')->nullable();  // INSERT-only; sin updated_at.

            $table->index(['business_id', 'currency', 'effective_from'], 'idx_exchange_rates_lookup');
        });

        DB::statement('ALTER TABLE exchange_rates ADD CONSTRAINT chk_exchange_rate_positive CHECK (rate > 0)');
        DB::statement("ALTER TABLE exchange_rates ADD CONSTRAINT chk_exchange_rate_not_base CHECK (currency <> '".Currency::base()->value."')");
    }

    public function down(): void
    {
        Schema::dropIfExists('exchange_rates');
    }
};
