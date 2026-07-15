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
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();

            // RESTRICT para no borrar una categoría con productos colgando
            $table->foreignId('category_id')->constrained('categories')->restrictOnDelete();

            // Opcional. SET NULL para borrar la marca deja el producto sin marca, no lo mata
            $table->foreignId('brand_id')->nullable()->constrained('brands')->nullOnDelete();

            $table->foreignId('unit_id')->constrained('units_of_measure')->restrictOnDelete();

            $table->string('sku', 60);
            $table->string('name', 160)->index();
            $table->enum('type', ['simple', 'compound', 'service'])->index(); // discriminador
            $table->decimal('sale_price', 12, 2)->default(0.00);
            $table->decimal('cost', 12, 2)->default(0.00);
            $table->boolean('tracks_inventory')->default(true);  // regla: service ⇒ false (capa app)
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['business_id', 'sku']);
            $table->index('deleted_at');
        });

        // CHECK de dominio: precio y costo nunca negativos. Enforced en MySQL 8.0.16+
        DB::statement('ALTER TABLE products ADD CONSTRAINT chk_products_sale_price CHECK (sale_price >= 0)');
        DB::statement('ALTER TABLE products ADD CONSTRAINT chk_products_cost CHECK (cost >= 0)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
