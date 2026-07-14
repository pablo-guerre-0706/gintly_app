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
        // Círculo 1 y business_id (Opción B): users referencia a businesses y branches
        Schema::table('users', function (Blueprint $table) {
            $table->foreign('business_id')->references('id')->on('businesses')
                  ->cascadeOnDelete();                          // borrar negocio, borra sus usuarios
            $table->foreign('branch_id')->references('id')->on('branches')
              ->nullOnDelete();                             // borrar sucursal, usuarios quedan sin ella
        });

        // Círculo 1 (lado inverso): businesses referencia al owner
        Schema::table('businesses', function (Blueprint $table) {
            $table->foreign('owner_user_id')->references('id')->on('users')
                  ->nullOnDelete();                             // borrar dueño, negocio queda sin owner
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['business_id']);
            $table->dropForeign(['branch_id']);
        });
        Schema::table('businesses', function (Blueprint $table) {
            $table->dropForeign(['owner_user_id']);
        });
    }
};
