<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // tasa impositiva
        DB::statement('
            ALTER TABLE businesses
            ADD CONSTRAINT chk_business_tax_rate
            CHECK (tax_rate >= 0 AND tax_rate < 1)
        ');

         // Unicidad de sucursal compatible con borrado lógico
        DB::statement("
            ALTER TABLE branches
            ADD COLUMN name_lock VARCHAR(150)
            GENERATED ALWAYS AS (CASE WHEN deleted_at IS NULL THEN name END) VIRTUAL
            AFTER is_active
        ");

        Schema::table('branches', function (Blueprint $table): void {
            $table->unique(['business_id', 'name_lock'], 'uniq_branch_active_name');
        });
    }

    public function down(): void
    {
        Schema::table('branches', function (Blueprint $table): void {
            $table->dropUnique('uniq_branch_active_name');
        });

        DB::statement('ALTER TABLE branches DROP COLUMN name_lock');

        DB::statement('ALTER TABLE businesses DROP CONSTRAINT chk_business_tax_rate');
    }
};
