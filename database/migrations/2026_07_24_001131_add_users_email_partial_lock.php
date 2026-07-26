<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;


// H-02 aplicado a `users`: unicidad de email compatible con borrado lógico.
return new class extends Migration
{
    private const OLD_UNIQUE = 'users_business_id_email_unique';

    private const FK_BACKING_INDEX = 'users_business_id_index';

    private const NEW_UNIQUE = 'uniq_user_active_email';

    public function up(): void
    {
        // 1. Índice de respaldo para la FK business_id, ANTES de soltar el único.
        if (! $this->indexExists(self::FK_BACKING_INDEX)) {
            Schema::table('users', function (Blueprint $table): void {
                $table->index('business_id', self::FK_BACKING_INDEX);
            });
        }

        // 2. Ahora la FK ya no depende del índice único: se puede eliminar.
        if ($this->indexExists(self::OLD_UNIQUE)) {
            Schema::table('users', function (Blueprint $table): void {
                $table->dropUnique(self::OLD_UNIQUE);
            });
        }

        // 3. Columna generada: vale el email solo mientras la fila está activa.
        //    MySQL no colisiona valores NULL en índices únicos, de modo que un
        //    usuario dado de baja libera su correo para un alta nueva.
        if (! $this->columnExists('email_lock')) {
            DB::statement("
                ALTER TABLE users
                ADD COLUMN email_lock VARCHAR(180)
                GENERATED ALWAYS AS (CASE WHEN deleted_at IS NULL THEN email END) VIRTUAL
                AFTER is_active
            ");
        }

        // 4. Candado parcial definitivo.
        if (! $this->indexExists(self::NEW_UNIQUE)) {
            Schema::table('users', function (Blueprint $table): void {
                $table->unique(['business_id', 'email_lock'], self::NEW_UNIQUE);
            });
        }
    }

    public function down(): void
    {
        // 4↩. Eliminar el candado parcial.
        if ($this->indexExists(self::NEW_UNIQUE)) {
            Schema::table('users', function (Blueprint $table): void {
                $table->dropUnique(self::NEW_UNIQUE);
            });
        }

        // 3↩. Eliminar la columna generada.
        if ($this->columnExists('email_lock')) {
            DB::statement('ALTER TABLE users DROP COLUMN email_lock');
        }

        // 2↩. Restaurar el índice único compuesto original.
        if (! $this->indexExists(self::OLD_UNIQUE)) {
            Schema::table('users', function (Blueprint $table): void {
                $table->unique(['business_id', 'email'], self::OLD_UNIQUE);
            });
        }

        // 1↩. Eliminar el índice de respaldo SOLO si el único compuesto ya
        //     puede volver a respaldar la FK. Si no existiera, se conserva
        //     el respaldo para no reintroducir el 1553 a la inversa.
        if ($this->indexExists(self::OLD_UNIQUE) && $this->indexExists(self::FK_BACKING_INDEX)) {
            Schema::table('users', function (Blueprint $table): void {
                $table->dropIndex(self::FK_BACKING_INDEX);
            });
        }
    }

    private function indexExists(string $index): bool
    {
        return DB::table('information_schema.STATISTICS')
            ->where('TABLE_SCHEMA', DB::getDatabaseName())
            ->where('TABLE_NAME', 'users')
            ->where('INDEX_NAME', $index)
            ->exists();
    }

    private function columnExists(string $column): bool
    {
        return Schema::hasColumn('users', $column);
    }
};
