<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;


return new class extends Migration
{
    public function up(): void
    {
        // counted_denominations JSON (evidencia del arqueo).
        if (! Schema::hasColumn('cash_sessions', 'counted_denominations')) {
            Schema::table('cash_sessions', function ($table): void {
                $table->json('counted_denominations')->nullable()->after('counted_amount');
            });
        }

        // difference como columna GENERADA STORED.
        if ($this->columnIsPlain('cash_sessions', 'difference')) {
            DB::statement('ALTER TABLE cash_sessions DROP COLUMN difference');
            DB::statement("
                ALTER TABLE cash_sessions
                ADD COLUMN difference DECIMAL(14,2)
                GENERATED ALWAYS AS (counted_amount - expected_amount) STORED
                AFTER counted_denominations
            ");
        }

        // open_user_lock: una sesión abierta por usuario.
        if (! Schema::hasColumn('cash_sessions', 'open_user_lock')) {
            DB::statement("
                ALTER TABLE cash_sessions
                ADD COLUMN open_user_lock BIGINT UNSIGNED
                GENERATED ALWAYS AS (CASE WHEN status = 'abierta' THEN opened_by END) VIRTUAL,
                ADD UNIQUE KEY uniq_open_session_per_user (open_user_lock)
            ");
        }

        // Coherencia de egreso autorizado.
        if (! $this->checkExists('cash_movements', 'chk_cash_movement_egreso_auth')) {
            DB::statement("
                ALTER TABLE cash_movements
                ADD CONSTRAINT chk_cash_movement_egreso_auth
                CHECK (category <> 'egreso_autorizado' OR authorized_by IS NOT NULL)
            ");
        }
    }

    public function down(): void
    {
        if ($this->checkExists('cash_movements', 'chk_cash_movement_egreso_auth')) {
            DB::statement('ALTER TABLE cash_movements DROP CONSTRAINT chk_cash_movement_egreso_auth');
        }

        if ($this->indexExists('cash_sessions', 'uniq_open_session_per_user')) {
            DB::statement('ALTER TABLE cash_sessions DROP INDEX uniq_open_session_per_user');
            DB::statement('ALTER TABLE cash_sessions DROP COLUMN open_user_lock');
        }

        // difference y counted_denominations no se revierten: son estructura de
        // datos de negocio que otros módulos ya consumen. down() conservador.
    }

    private function columnIsPlain(string $table, string $column): bool
    {
        $row = DB::selectOne("
            SELECT EXTRA FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?
        ", [DB::getDatabaseName(), $table, $column]);

        if ($row === null) {
            return false;
        }

        return ! str_contains(strtoupper((string) $row->EXTRA), 'GENERATED');
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

    private function indexExists(string $table, string $index): bool
    {
        return DB::table('information_schema.STATISTICS')
            ->where('TABLE_SCHEMA', DB::getDatabaseName())
            ->where('TABLE_NAME', $table)
            ->where('INDEX_NAME', $index)
            ->exists();
    }
};

