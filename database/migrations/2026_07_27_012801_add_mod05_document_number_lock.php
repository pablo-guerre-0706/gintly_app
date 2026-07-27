<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;


// Candado parcial de document_number de cliente compatible con borrado lógico, NULL múltiples.

return new class extends Migration
{
    private const OLD_UNIQUE = 'customers_business_id_document_number_unique';

    private const BACKING = 'customers_business_id_index';

    private const NEW_UNIQUE = 'uniq_active_customer_document';

    public function up(): void
    {
        // Índice de respaldo para la FK business_id ANTES de soltar el único.
        if (! $this->indexExists('customers', self::BACKING)) {
            Schema::table('customers', function (Blueprint $table): void {
                $table->index('business_id', self::BACKING);
            });
        }

        // Soltar el UQ compuesto plano.
        if ($this->indexExists('customers', self::OLD_UNIQUE)) {
            Schema::table('customers', function (Blueprint $table): void {
                $table->dropUnique(self::OLD_UNIQUE);
            });
        }

        // Columna generada: documento solo si la fila está activa Y lo tiene.
        if (! Schema::hasColumn('customers', 'document_number_lock')) {
            DB::statement("
                ALTER TABLE customers
                ADD COLUMN document_number_lock VARCHAR(30)
                GENERATED ALWAYS AS (
                    CASE WHEN deleted_at IS NULL AND document_number IS NOT NULL THEN document_number END
                ) VIRTUAL
                AFTER document_number
            ");
        }

        // Candado parcial definitivo.
        if (! $this->indexExists('customers', self::NEW_UNIQUE)) {
            Schema::table('customers', function (Blueprint $table): void {
                $table->unique(['business_id', 'document_number_lock'], self::NEW_UNIQUE);
            });
        }
    }

    public function down(): void
    {
        if ($this->indexExists('customers', self::NEW_UNIQUE)) {
            Schema::table('customers', function (Blueprint $table): void {
                $table->dropUnique(self::NEW_UNIQUE);
            });
        }

        if (Schema::hasColumn('customers', 'document_number_lock')) {
            DB::statement('ALTER TABLE customers DROP COLUMN document_number_lock');
        }

        if (! $this->indexExists('customers', self::OLD_UNIQUE)) {
            Schema::table('customers', function (Blueprint $table): void {
                $table->unique(['business_id', 'document_number'], self::OLD_UNIQUE);
            });
        }

        if ($this->indexExists('customers', self::OLD_UNIQUE) && $this->indexExists('customers', self::BACKING)) {
            Schema::table('customers', function (Blueprint $table): void {
                $table->dropIndex(self::BACKING);
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
};
