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
        Schema::create('product_recipes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();

            // Si se borra el compuesto, tambien se borra la receta (CASCADE).
            $table->foreignId('compound_id')->constrained('products')->cascadeOnDelete();

            // restrict. No borras un producto que es ingrediente de algo.
            $table->foreignId('ingredient_id')->constrained('products')->restrictOnDelete();

            $table->decimal('quantity', 12, 3);
            $table->foreignId('unit_id')->constrained('units_of_measure')->restrictOnDelete();

            // Un insumo no se repite dos veces en la misma receta
            $table->unique(['compound_id', 'ingredient_id']);
        });

        // CHECK de dominio para evitar que exista una cantidad de insumo cero o negativa
        DB::statement('ALTER TABLE product_recipes ADD CONSTRAINT chk_recipe_quantity
            CHECK (quantity > 0)');
        // FIX auditoría MOD-02: guarda de auto-composición directa a nivel de motor (RF-02-05).
        DB::statement('ALTER TABLE product_recipes ADD CONSTRAINT chk_recipe_no_self
            CHECK (compound_id <> ingredient_id)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_recipes');
    }
};
