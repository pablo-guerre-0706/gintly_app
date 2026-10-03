<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * MOD-12 · Convergencia REPRODUCIBLE del índice uniq_business_goal (microcierre).
 *
 * La migración de creación (2026_07_18_193313_create_business_goals_table) ya define el UNIQUE con
 * las cinco columnas (business_id, branch_key, kpi_code, period_type, period_start), de modo que un
 * proyecto nuevo obtiene la estructura correcta al migrar en limpio. Esta migración correctiva
 * garantiza además que un entorno que hubiera aplicado una versión ANTERIOR de aquella creación con
 * solo cuatro columnas (business_id, branch_key, kpi_code, period_start) converja a la definición
 * definitiva sin perder datos, ya que una migración no se re-ejecuta y editar la de creación no
 * arregla las bases ya migradas.
 *
 * Es IDEMPOTENTE: si el índice ya tiene las cinco columnas (caso de esta copia y de toda instalación
 * nueva) no hace nada. Al pasar de 4 → 5 columnas la unicidad se RELAJA (más columnas admiten más
 * combinaciones), así que ningún dato existente puede violar el índice nuevo: es preservadora.
 *
 * SEGURIDAD frente a MySQL 1553 ("cannot drop index needed in a foreign key constraint"): el índice
 * uniq_business_goal es el único que encabeza con business_id, por lo que soporta la FK de negocio.
 * Se crea un índice de respaldo sobre business_id ANTES de soltar el UNIQUE y se retira DESPUÉS de
 * recrearlo (el nuevo UNIQUE vuelve a encabezar con business_id y soporta la FK).
 */
return new class extends Migration
{
    private const TARGET = ['business_id', 'branch_key', 'kpi_code', 'period_type', 'period_start'];

    public function up(): void
    {
        if (! Schema::hasTable('business_goals')) {
            return;
        }

        // Idempotencia: ya es el índice definitivo ⇒ no se toca nada.
        if ($this->uniqueColumns() === self::TARGET) {
            return;
        }

        // 1) Respaldo de la FK business_id ANTES de soltar el UNIQUE (evita 1553).
        if (! $this->indexExists('idx_bg_business_fk_backup')) {
            DB::statement('ALTER TABLE `business_goals` ADD INDEX `idx_bg_business_fk_backup` (`business_id`)');
        }

        // 2) Soltar el UNIQUE antiguo (si existe con otra composición).
        if ($this->indexExists('uniq_business_goal')) {
            DB::statement('ALTER TABLE `business_goals` DROP INDEX `uniq_business_goal`');
        }

        // 3) Recrear el UNIQUE definitivo de cinco columnas (period_type incluido).
        DB::statement(
            'ALTER TABLE `business_goals` ADD UNIQUE `uniq_business_goal` '
            . '(`business_id`, `branch_key`, `kpi_code`, `period_type`, `period_start`)'
        );

        // 4) Retirar el respaldo: el nuevo UNIQUE vuelve a soportar la FK business_id.
        if ($this->indexExists('idx_bg_business_fk_backup')) {
            DB::statement('ALTER TABLE `business_goals` DROP INDEX `idx_bg_business_fk_backup`');
        }
    }

    public function down(): void
    {
        // Revertir a cuatro columnas es INSEGURO (podría existir data con mismo inicio y distinto
        // period_type que violaría el índice más restrictivo). La convergencia es forward-only.
    }

    /** @return array<int, string> Columnas del índice uniq_business_goal en orden de secuencia. */
    private function uniqueColumns(): array
    {
        $rows = DB::select(
            'SELECT COLUMN_NAME FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ? ORDER BY SEQ_IN_INDEX',
            ['business_goals', 'uniq_business_goal']
        );

        return array_map(static fn ($r) => $r->COLUMN_NAME, $rows);
    }

    private function indexExists(string $name): bool
    {
        return DB::table('information_schema.STATISTICS')
            ->whereRaw('TABLE_SCHEMA = DATABASE()')
            ->where('TABLE_NAME', 'business_goals')
            ->where('INDEX_NAME', $name)
            ->exists();
    }
};
