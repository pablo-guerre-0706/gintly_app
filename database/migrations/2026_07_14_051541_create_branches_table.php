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
        Schema::create('branches', function (Blueprint $table) {
            $table->id();
            // FK inline: businesses existe cuando corre esta migración (timestamp mayor)
            $table->foreignId('business_id')
                  ->constrained('businesses')->cascadeOnDelete();   // borrar business, borra branches

            $table->string('name', 150);
            $table->string('address', 255);                         // acreditación real (antifraude)

            // FK inline: users existe. Nullable → nullOnDelete
            $table->foreignId('manager_user_id')->nullable()
                ->constrained('users')->nullOnDelete();

            $table->date('opened_at');                              // requisito antifraude
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->index('deleted_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('branches');
    }
};
