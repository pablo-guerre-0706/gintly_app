<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // RESTRICT por coherencia con los otros orígenes del kardex
        // un movimiento de inventario nunca pierde su documento de origen.
        Schema::table('inventory_movements', function (Blueprint $table) {
            $table->foreign('dispatch_id')
                ->references('id')->on('dispatches')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        // Reversible: suelta la FK, conserva la columna (vuelve al estado "plana sin constraint").
        Schema::table('inventory_movements', function (Blueprint $table) {
            $table->dropForeign(['dispatch_id']);
        });
    }
};
