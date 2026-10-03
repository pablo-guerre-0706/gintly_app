<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Reconciliación MOD-02.
 *
 * 1) `products.is_taxable` (boolean NN, def. true — Diccionario de Datos §products):
 *    el modelo, los FormRequest, el Resource y el flujo de ventas ya la usan, pero
 *    ninguna migración la creaba. En una base recién migrada la columna no existía
 *    y toda escritura con is_taxable fallaba (columna desconocida). Se añade de forma
 *    idempotente para no chocar con entornos que ya la tuvieran fuera de migración.
 *
 * 2) `chk_service_no_inventory` (Diccionario de Datos §products, CHECK):
 *    refuerza a nivel de motor la regla que la capa de aplicación ya coacciona
 *    (type=service ⇒ tracks_inventory=false). Defensa en profundidad.
 *
 * MySQL-only: CHECK y information_schema. No verificable bajo SQLite.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('products', 'is_taxable')) {
            Schema::table('products', function (Blueprint $table): void {
                $table->boolean('is_taxable')->default(true)->after('tracks_inventory');
            });
        }

        if (! $this->checkExists('chk_service_no_inventory')) {
            // Un servicio jamás rastrea inventario. La app ya lo garantiza en saving();
            // esta guarda lo blinda también ante escrituras directas (job/consola).
            DB::statement("
                ALTER TABLE products
                ADD CONSTRAINT chk_service_no_inventory
                CHECK (type <> 'service' OR tracks_inventory = 0)
            ");
        }
    }

    public function down(): void
    {
        if ($this->checkExists('chk_service_no_inventory')) {
            DB::statement('ALTER TABLE products DROP CONSTRAINT chk_service_no_inventory');
        }

        if (Schema::hasColumn('products', 'is_taxable')) {
            Schema::table('products', function (Blueprint $table): void {
                $table->dropColumn('is_taxable');
            });
        }
    }

    private function checkExists(string $constraint): bool
    {
        return DB::table('information_schema.TABLE_CONSTRAINTS')
            ->where('TABLE_SCHEMA', DB::getDatabaseName())
            ->where('TABLE_NAME', 'products')
            ->where('CONSTRAINT_NAME', $constraint)
            ->where('CONSTRAINT_TYPE', 'CHECK')
            ->exists();
    }
};
