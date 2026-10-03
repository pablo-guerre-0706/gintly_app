<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;


return new class extends Migration
{
    private const WH_OLD_UNIQUE = 'warehouses_business_id_branch_id_name_unique';

    private const WH_BACKING = 'warehouses_business_id_index';

    private const WH_NEW_UNIQUE = 'uniq_active_warehouse_name';

    public function up(): void
    {
        // warehouses. Índice de respaldo para la FK business_id ANTES de soltar el único.
        if (! $this->indexExists('warehouses', self::WH_BACKING)) {
            Schema::table('warehouses', function (Blueprint $table): void {
                $table->index('business_id', self::WH_BACKING);
            });
        }

        // Soltar el UQ compuesto plano.
        if ($this->indexExists('warehouses', self::WH_OLD_UNIQUE)) {
            Schema::table('warehouses', function (Blueprint $table): void {
                $table->dropUnique(self::WH_OLD_UNIQUE);
            });
        }

        // Columna generada name_lock: el nombre solo si la fila está activa.
        if (! Schema::hasColumn('warehouses', 'name_lock')) {
            DB::statement("
                ALTER TABLE warehouses
                ADD COLUMN name_lock VARCHAR(120)
                GENERATED ALWAYS AS (CASE WHEN deleted_at IS NULL THEN name END) VIRTUAL
                AFTER is_active
            ");
        }

        // Candado parcial, único por (negocio, sucursal, nombre activo).
        if (! $this->indexExists('warehouses', self::WH_NEW_UNIQUE)) {
            Schema::table('warehouses', function (Blueprint $table): void {
                $table->unique(['business_id', 'branch_id', 'name_lock'], self::WH_NEW_UNIQUE);
            });
        }

        // Stock_levels. Ambos umbrales son NULL-ables; el CHECK solo aplica cuando ambos existen.
        if (! $this->checkExists('stock_levels', 'chk_stock_min_le_max')) {
            DB::statement('
                ALTER TABLE stock_levels
                ADD CONSTRAINT chk_stock_min_le_max
                CHECK (min_stock IS NULL OR max_stock IS NULL OR min_stock <= max_stock)
            ');
        }
    }

    public function down(): void
    {
        if ($this->checkExists('stock_levels', 'chk_stock_min_le_max')) {
            DB::statement('ALTER TABLE stock_levels DROP CONSTRAINT chk_stock_min_le_max');
        }

        if ($this->indexExists('warehouses', self::WH_NEW_UNIQUE)) {
            Schema::table('warehouses', function (Blueprint $table): void {
                $table->dropUnique(self::WH_NEW_UNIQUE);
            });
        }

        if (Schema::hasColumn('warehouses', 'name_lock')) {
            DB::statement('ALTER TABLE warehouses DROP COLUMN name_lock');
        }

        if (! $this->indexExists('warehouses', self::WH_OLD_UNIQUE)) {
            Schema::table('warehouses', function (Blueprint $table): void {
                $table->unique(['business_id', 'branch_id', 'name'], self::WH_OLD_UNIQUE);
            });
        }

        if ($this->indexExists('warehouses', self::WH_OLD_UNIQUE) && $this->indexExists('warehouses', self::WH_BACKING)) {
            Schema::table('warehouses', function (Blueprint $table): void {
                $table->dropIndex(self::WH_BACKING);
            });
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

    private function checkExists(string $table, string $constraint): bool
    {
        return DB::table('information_schema.TABLE_CONSTRAINTS')
            ->where('TABLE_SCHEMA', DB::getDatabaseName())
            ->where('TABLE_NAME', $table)
            ->where('CONSTRAINT_NAME', $constraint)
            ->where('CONSTRAINT_TYPE', 'CHECK')
            ->exists();
    }
};
