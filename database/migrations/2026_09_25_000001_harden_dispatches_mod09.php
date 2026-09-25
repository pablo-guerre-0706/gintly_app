<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * MOD-09 · Endurecimiento de `dispatches` (microcierre de integridad).
 *
 *  (1) received_by NOT NULL: RF-09-01 exige que TODO retiro registre un "receptor declarado".
 *      El contrato/columna anterior lo dejó nullable; se corrige a garantía de motor. La capa
 *      HTTP (StoreDispatchRequest) ya lo exige, así que ninguna vía crea filas sin receptor.
 *      Guardado: si existieran filas NULL previas, aborta con mensaje controlado (no inventa dato).
 *
 *  (2) Índice por fecha (dispatched_at): soporta el filtro from/to del listado sin escaneo.
 *
 * Idempotente. Sin migrate:fresh.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('dispatches', 'received_by')) {
            return;
        }

        // (1) received_by NOT NULL.
        if ($this->columnIsNullable('received_by')) {
            $orphans = DB::table('dispatches')->whereNull('received_by')->count();
            if ($orphans > 0) {
                throw new RuntimeException(
                    "MOD-09 abortado: {$orphans} retiros sin receptor declarado (received_by NULL). "
                    . 'Regularice el dato antes de aplicar la restricción NOT NULL; no se inventa el receptor.'
                );
            }

            DB::statement('ALTER TABLE `dispatches` MODIFY `received_by` VARCHAR(160) NOT NULL');
        }

        // (2) Índice por fecha del retiro.
        if (! $this->indexExists('idx_dispatch_dispatched_at')) {
            Schema::table('dispatches', function (Blueprint $table): void {
                $table->index('dispatched_at', 'idx_dispatch_dispatched_at');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('dispatches', 'received_by')) {
            return;
        }

        if ($this->indexExists('idx_dispatch_dispatched_at')) {
            Schema::table('dispatches', function (Blueprint $table): void {
                $table->dropIndex('idx_dispatch_dispatched_at');
            });
        }

        if (! $this->columnIsNullable('received_by')) {
            DB::statement('ALTER TABLE `dispatches` MODIFY `received_by` VARCHAR(160) NULL');
        }
    }

    private function indexExists(string $indexName): bool
    {
        return DB::table('information_schema.STATISTICS')
            ->whereRaw('TABLE_SCHEMA = DATABASE()')
            ->where('TABLE_NAME', 'dispatches')
            ->where('INDEX_NAME', $indexName)
            ->exists();
    }

    private function columnIsNullable(string $column): bool
    {
        $col = DB::selectOne(
            'SELECT IS_NULLABLE FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            ['dispatches', $column]
        );

        return $col !== null && strtoupper((string) $col->IS_NULLABLE) === 'YES';
    }
};
