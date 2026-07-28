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
        // La columna dispatch_id ya existe desde MOD-03 (reservada). Aquí SOLO se cablea la FK.
        $alreadyWired = collect(DB::select(
            "SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'inventory_movements'
               AND COLUMN_NAME = 'dispatch_id' AND REFERENCED_TABLE_NAME = 'dispatches'"
        ))->isNotEmpty();

        if ($alreadyWired || ! Schema::hasColumn('inventory_movements', 'dispatch_id')) {
            return; // Idempotencia.
        }

        Schema::table('inventory_movements', function (Blueprint $table): void {
            $table->foreign('dispatch_id', 'fk_movements_dispatch')
                ->references('id')->on('dispatches')
                ->restrictOnDelete(); // Origen del asiento: no se borra un dispatch con kardex.
        });
    }

    public function down(): void
    {
        Schema::table('inventory_movements', function (Blueprint $table): void {
            $table->dropForeign('fk_movements_dispatch');
        });
    }
};
