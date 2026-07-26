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
        Schema::create('businesses', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('slug', 160)->unique();                 // identificador URL-safe único
            $table->unsignedBigInteger('owner_user_id')->nullable(); // columna sola; FK diferida
            $table->string('plan', 50)->default('basic');
            // IANA tz; overridable por negocio/país
            $table->string('timezone', 64)->default('America/Managua');
            // IVA por defecto (Nic. 15%). Parametrizable por país
            $table->decimal('tax_rate', 5, 4)->default(0.1500);
            $table->enum('status', ['active', 'suspended', 'trial'])
              ->default('trial')->index();                     // estado de cuenta, indexado
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
        Schema::dropIfExists('businesses');
    }
};
