<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;


// Unicidad de categorías y marcas compatible con borrado lógico.
// products.sku queda con índice plano perpetuo (H-15), no se toca aquí.
return new class extends Migration
{
    /**
     * @var array<int, array{table: string, old: string, backing: string, new: string}>
     */
    private array $targets = [
        [
            'table'   => 'categories',
            'old'     => 'categories_business_id_name_unique',
            'backing' => 'categories_business_id_index',
            'new'     => 'uniq_active_category_name',
        ],
        [
            'table'   => 'brands',
            'old'     => 'brands_business_id_name_unique',
            'backing' => 'brands_business_id_index',
            'new'     => 'uniq_active_brand_name',
        ],
    ];

    public function up(): void
    {
        foreach ($this->targets as $t) {
            // 1. Índice de respaldo para la FK business_id, ANTES de soltar el único.
            if (! $this->indexExists($t['table'], $t['backing'])) {
                Schema::table($t['table'], function (Blueprint $table) use ($t): void {
                    $table->index('business_id', $t['backing']);
                });
            }

            // 2. Ahora la FK ya no depende del índice único: se puede soltar.
            if ($this->indexExists($t['table'], $t['old'])) {
                Schema::table($t['table'], function (Blueprint $table) use ($t): void {
                    $table->dropUnique($t['old']);
                });
            }

            // 3. Columna generada name_lock: el nombre solo si la fila está activa.
            if (! Schema::hasColumn($t['table'], 'name_lock')) {
                DB::statement("
                    ALTER TABLE {$t['table']}
                    ADD COLUMN name_lock VARCHAR(120)
                    GENERATED ALWAYS AS (CASE WHEN deleted_at IS NULL THEN name END) VIRTUAL
                    AFTER is_active
                ");
            }

            // 4. Candado parcial definitivo.
            if (! $this->indexExists($t['table'], $t['new'])) {
                Schema::table($t['table'], function (Blueprint $table) use ($t): void {
                    $table->unique(['business_id', 'name_lock'], $t['new']);
                });
            }
        }
    }

    public function down(): void
    {
        foreach ($this->targets as $t) {
            // 4↩. Soltar el candado parcial.
            if ($this->indexExists($t['table'], $t['new'])) {
                Schema::table($t['table'], function (Blueprint $table) use ($t): void {
                    $table->dropUnique($t['new']);
                });
            }

            // 3↩. Soltar la columna generada.
            if (Schema::hasColumn($t['table'], 'name_lock')) {
                DB::statement("ALTER TABLE {$t['table']} DROP COLUMN name_lock");
            }

            // 2↩. Restaurar el UNIQUE compuesto original.
            if (! $this->indexExists($t['table'], $t['old'])) {
                Schema::table($t['table'], function (Blueprint $table) use ($t): void {
                    $table->unique(['business_id', 'name'], $t['old']);
                });
            }

            // 1↩. Soltar el índice de respaldo SOLO si el compuesto ya volvió a
            //     existir para respaldar la FK. Si no, se conserva para no
            //     reintroducir el 1553 a la inversa.
            if ($this->indexExists($t['table'], $t['old']) && $this->indexExists($t['table'], $t['backing'])) {
                Schema::table($t['table'], function (Blueprint $table) use ($t): void {
                    $table->dropIndex($t['backing']);
                });
            }
        }
    }

    private function indexExists(string $table, string $index): bool
    {
        return DB::table('information_schema.STATISTICS')
            ->where('TABLE_SCHEMA', DB::getDatabaseName())
            ->where('TABLE_NAME', $table)
            ->where('INDEX_NAME', $index)
            ->exists();
    }
};

