<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reconciliación: tabla `sequences` (contador de folio atómico por negocio y tipo),
 * consumida por App\Support\SequenceGenerator para los folios de órdenes de compra
 * ('OC-', MOD-04) y traspasos ('TR-', MOD-03). Ninguna migración la creaba, por lo
 * que POST /purchase-orders y POST /stock-transfers fallaban con "Table 'sequences'
 * doesn't exist". Se descubrió al ejecutar las pruebas HTTP de MOD-04.
 *
 * Columnas según el uso real del generador: (business_id, type) único; next_value
 * arranca en 1 (el generador inserta next_value=2 y devuelve 1 en el primer folio).
 * El contrato MOD-03 la describe: "Contador de folio atómico por (business_id, type)".
 * Idempotente (hasTable).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('sequences')) {
            return;
        }

        Schema::create('sequences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->string('type', 50);
            $table->unsignedBigInteger('next_value')->default(1);
            $table->timestamps();

            $table->unique(['business_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sequences');
    }
};
