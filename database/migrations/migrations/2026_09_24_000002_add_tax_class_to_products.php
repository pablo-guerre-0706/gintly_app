<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MOD-07 · Clase fiscal explícita del producto.
 *
 * Se añade nullable en este paso; el backfill (migración posterior) la puebla desde
 * el is_taxable actual y luego la vuelve NOT NULL antes de retirar is_taxable, para
 * no dejar dos fuentes fiscales escribibles.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->enum('tax_class', ['standard', 'reduced', 'zero_rated', 'exempt'])
                ->nullable()
                ->after('tracks_inventory')
                ->index();
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->dropColumn('tax_class');
        });
    }
};
