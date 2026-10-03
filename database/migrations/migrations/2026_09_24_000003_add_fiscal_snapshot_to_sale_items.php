<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * MOD-07 · Fotografía fiscal inmutable por línea de venta.
 *
 * Al agregar la línea se congela, junto a descripción/precio/costo/receta, la
 * clase fiscal, la condición (gravado/tasa cero/exento), la tasa aplicada, la base
 * gravable, el impuesto de la línea y la regla fiscal usada (auditoría). La factura
 * suma estos importes congelados; un cambio posterior de producto/tasa/sucursal no
 * altera una venta confirmada ni una factura emitida.
 *
 * Nullable/con default porque las filas históricas (previas a la función) no tienen
 * snapshot; el backfill posterior las completa de forma consistente.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sale_items', function (Blueprint $table): void {
            $table->string('tax_class', 20)->nullable()->after('is_taxable');
            $table->string('fiscal_condition', 20)->nullable()->after('tax_class');
            $table->decimal('tax_rate', 8, 6)->default(0)->after('fiscal_condition');
            $table->decimal('taxable_base', 14, 2)->default(0)->after('tax_rate');
            $table->decimal('tax_amount', 14, 2)->default(0)->after('taxable_base');
            // Regla fiscal aplicada (auditoría). RESTRICT: una regla usada no se borra.
            $table->foreignId('tax_rule_id')->nullable()->after('tax_amount')
                ->constrained('tax_rules')->restrictOnDelete();
        });

        DB::statement('ALTER TABLE sale_items ADD CONSTRAINT chk_sale_item_tax_rate
            CHECK (tax_rate >= 0 AND tax_rate <= 1)');
        DB::statement('ALTER TABLE sale_items ADD CONSTRAINT chk_sale_item_taxable_base_non_negative
            CHECK (taxable_base >= 0)');
        DB::statement('ALTER TABLE sale_items ADD CONSTRAINT chk_sale_item_tax_amount_non_negative
            CHECK (tax_amount >= 0)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE sale_items DROP CONSTRAINT chk_sale_item_tax_amount_non_negative');
        DB::statement('ALTER TABLE sale_items DROP CONSTRAINT chk_sale_item_taxable_base_non_negative');
        DB::statement('ALTER TABLE sale_items DROP CONSTRAINT chk_sale_item_tax_rate');

        Schema::table('sale_items', function (Blueprint $table): void {
            $table->dropForeign(['tax_rule_id']);
            $table->dropColumn(['tax_class', 'fiscal_condition', 'tax_rate', 'taxable_base', 'tax_amount', 'tax_rule_id']);
        });
    }
};
