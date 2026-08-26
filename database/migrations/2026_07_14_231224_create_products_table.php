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
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->foreignId('category_id')->constrained('categories')->restrictOnDelete();
            $table->foreignId('brand_id')->nullable()->constrained('brands')->nullOnDelete();
            $table->foreignId('unit_id')->constrained('units_of_measure')->restrictOnDelete();

            $table->string('sku', 60);
            $table->string('name', 160)->index();
            $table->enum('type', ['simple', 'compound', 'service'])->index();
            
            // Restricciones CHECK declaradas inline (compatibles con SQLite y MySQL)
            $table->decimal('sale_price', 12, 2)->default(0.00)->check('sale_price >= 0');
            $table->decimal('cost', 12, 2)->default(0.00)->check('cost >= 0');
            
            $table->boolean('is_taxable')->default(true);
            $table->boolean('tracks_inventory')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['business_id', 'sku']);
            $table->index('deleted_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
