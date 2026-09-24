<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * MOD-07 · Backfill fiscal: continuidad exacta con el esquema anterior.
 *
 *  1. Cada negocio recibe una regla fiscal ESTÁNDAR general activa = su business.tax_rate
 *     actual (equivalencia 1:1 con el comportamiento previo).
 *  2. products.tax_class se deriva del is_taxable actual (true→standard, false→exempt)
 *     y luego se vuelve NOT NULL.
 *  3. Las líneas de venta históricas reciben una fotografía fiscal consistente.
 *  4. products.is_taxable se ELIMINA como columna escribible; el modelo la reexpone
 *     como accesor derivado de tax_class → una sola fuente de verdad fiscal.
 *
 * Forward-only, preservando datos. MySQL-only (UPDATE ... JOIN, ROUND, ENUM).
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1) Regla estándar general por negocio (idempotente por si ya existiera).
        DB::statement("
            INSERT INTO tax_rules (business_id, tax_class, branch_id, rate, is_active, created_at, updated_at)
            SELECT b.id, 'standard', NULL, b.tax_rate, 1, NOW(), NOW()
            FROM businesses b
            WHERE NOT EXISTS (
                SELECT 1 FROM tax_rules tr
                WHERE tr.business_id = b.id AND tr.tax_class = 'standard'
                  AND tr.branch_id IS NULL AND tr.is_active = 1
            )
        ");

        // 2) Clase fiscal del producto desde is_taxable.
        DB::statement("
            UPDATE products
            SET tax_class = IF(is_taxable = 1, 'standard', 'exempt')
            WHERE tax_class IS NULL
        ");

        // Ahora que toda fila tiene clase, la columna es obligatoria.
        DB::statement("
            ALTER TABLE products
            MODIFY COLUMN tax_class ENUM('standard','reduced','zero_rated','exempt') NOT NULL
        ");

        // 3) Fotografía fiscal de líneas históricas. Estrategia DETERMINISTA de
        //    asignación, coherente con el esquema anterior (is_taxable + business.tax_rate):
        //      - is_taxable=1 → standard/gravado, tasa = business.tax_rate, base = line_total,
        //        impuesto = ROUND(line_total × tasa, 2);
        //      - is_taxable=0 → exempt/exento, base 0, impuesto 0.
        //    NO se toca invoices.tax_amount: la cifra fiscal ORIGINAL de cada factura
        //    histórica se CONSERVA intacta (es la autoritativa para documentos ya emitidos).
        //    Si la suma por línea reconstruida difiriera en céntimos de invoices.tax_amount
        //    (posible por el cambio truncación→redondeo del método anterior), prevalece la
        //    cifra original de la factura; los snapshots por línea son evidencia de auditoría,
        //    no recalculan el documento. (En la base autorizada no había datos históricos, por
        //    lo que este UPDATE fue no-op; se documenta como incidencia controlada de migración.)
        DB::statement("
            UPDATE sale_items si
            JOIN businesses b ON b.id = si.business_id
            SET si.tax_class        = IF(si.is_taxable = 1, 'standard', 'exempt'),
                si.fiscal_condition = IF(si.is_taxable = 1, 'gravado', 'exento'),
                si.tax_rate         = IF(si.is_taxable = 1, b.tax_rate, 0),
                si.taxable_base     = IF(si.is_taxable = 1, si.line_total, 0),
                si.tax_amount       = IF(si.is_taxable = 1, ROUND(si.line_total * b.tax_rate, 2), 0)
            WHERE si.tax_class IS NULL
        ");

        // 4) Retirar la segunda fuente fiscal escribible. is_taxable pasa a accesor derivado.
        Schema::table('products', function (Blueprint $table): void {
            $table->dropColumn('is_taxable');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->boolean('is_taxable')->default(true)->after('tracks_inventory');
        });

        // Restituye is_taxable desde la clase fiscal (exento → false; resto → true).
        DB::statement("UPDATE products SET is_taxable = IF(tax_class = 'exempt', 0, 1)");

        DB::statement("
            ALTER TABLE products
            MODIFY COLUMN tax_class ENUM('standard','reduced','zero_rated','exempt') NULL
        ");

        // Las reglas estándar sembradas y los snapshots se retiran al revertir sus
        // propias migraciones (000001 / 000003); aquí solo se revierte products.
    }
};
