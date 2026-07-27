<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;


return new class extends Migration
{
    private const SUP_OLD_UNIQUE = 'suppliers_business_id_tax_id_unique';

    private const SUP_BACKING = 'suppliers_business_id_index';

    private const SUP_NEW_UNIQUE = 'uniq_active_supplier_tax_id';

    public function up(): void
    {
        // suppliers.tax_id
        if (! $this->indexExists('suppliers', self::SUP_BACKING)) {
            Schema::table('suppliers', function (Blueprint $table): void {
                $table->index('business_id', self::SUP_BACKING);
            });
        }

        if ($this->indexExists('suppliers', self::SUP_OLD_UNIQUE)) {
            Schema::table('suppliers', function (Blueprint $table): void {
                $table->dropUnique(self::SUP_OLD_UNIQUE);
            });
        }

        // Vale tax_id solo si la fila está activa Y tiene RUC. NULL en otro caso.
        if (! Schema::hasColumn('suppliers', 'tax_id_lock')) {
            DB::statement("
                ALTER TABLE suppliers
                ADD COLUMN tax_id_lock VARCHAR(30)
                GENERATED ALWAYS AS (
                    CASE WHEN deleted_at IS NULL AND tax_id IS NOT NULL THEN tax_id END
                ) VIRTUAL
                AFTER tax_id
            ");
        }

        if (! $this->indexExists('suppliers', self::SUP_NEW_UNIQUE)) {
            Schema::table('suppliers', function (Blueprint $table): void {
                $table->unique(['business_id', 'tax_id_lock'], self::SUP_NEW_UNIQUE);
            });
        }

        // Cableado FK inventory_movements.purchase_order_id
        if (Schema::hasColumn('inventory_movements', 'purchase_order_id')
            && ! $this->foreignKeyExists('inventory_movements', 'inventory_movements_purchase_order_id_foreign')
        ) {
            Schema::table('inventory_movements', function (Blueprint $table): void {
                $table->foreign('purchase_order_id', 'inventory_movements_purchase_order_id_foreign')
                    ->references('id')->on('purchase_orders')
                    ->restrictOnDelete();
            });
        }
    }

    public function down(): void
    {
        if ($this->foreignKeyExists('inventory_movements', 'inventory_movements_purchase_order_id_foreign')) {
            Schema::table('inventory_movements', function (Blueprint $table): void {
                $table->dropForeign('inventory_movements_purchase_order_id_foreign');
            });
        }

        if ($this->indexExists('suppliers', self::SUP_NEW_UNIQUE)) {
            Schema::table('suppliers', function (Blueprint $table): void {
                $table->dropUnique(self::SUP_NEW_UNIQUE);
            });
        }

        if (Schema::hasColumn('suppliers', 'tax_id_lock')) {
            DB::statement('ALTER TABLE suppliers DROP COLUMN tax_id_lock');
        }

        if (! $this->indexExists('suppliers', self::SUP_OLD_UNIQUE)) {
            Schema::table('suppliers', function (Blueprint $table): void {
                $table->unique(['business_id', 'tax_id'], self::SUP_OLD_UNIQUE);
            });
        }

        if ($this->indexExists('suppliers', self::SUP_OLD_UNIQUE) && $this->indexExists('suppliers', self::SUP_BACKING)) {
            Schema::table('suppliers', function (Blueprint $table): void {
                $table->dropIndex(self::SUP_BACKING);
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

    private function foreignKeyExists(string $table, string $constraint): bool
    {
        return DB::table('information_schema.TABLE_CONSTRAINTS')
            ->where('TABLE_SCHEMA', DB::getDatabaseName())
            ->where('TABLE_NAME', $table)
            ->where('CONSTRAINT_NAME', $constraint)
            ->where('CONSTRAINT_TYPE', 'FOREIGN KEY')
            ->exists();
    }
};
