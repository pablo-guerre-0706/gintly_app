<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('warehouses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            // RESTRICT: no borras una sucursal que tiene bodegas colgando
            $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();
            $table->string('name', 120);
            $table->boolean('is_default')->default(false);  // bodega por defecto del POS
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['business_id', 'branch_id', 'name']);
            $table->index('deleted_at');
        });

        DB::statement("ALTER TABLE warehouses
            ADD COLUMN default_lock BIGINT UNSIGNED
            GENERATED ALWAYS AS (CASE WHEN is_default = 1 THEN branch_id END) VIRTUAL,
            ADD UNIQUE KEY uniq_default_warehouse_per_branch (default_lock)");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('warehouses');
    }
};
