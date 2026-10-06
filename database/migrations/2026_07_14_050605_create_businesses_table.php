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
            $table->string('slug', 160)->unique();                    // Identificador URL-safe único
            $table->unsignedBigInteger('owner_user_id')->nullable(); // Columna sola; FK diferida
            
            // Campos de ubicación e identificación fiscal (Paso 2 del asistente)
            $table->string('ruc', 30)->unique()->nullable();
            $table->string('country_region', 100)->nullable();
            $table->string('city', 100)->nullable();
            $table->string('codigo_postal', 20)->nullable();
            $table->string('address', 255)->nullable();
            $table->string('correo_tienda', 150)->nullable();
            $table->string('numero_convencional', 20)->nullable();
            $table->integer('numero_sucursales')->default(1);
            
            // Campos comerciales y de configuración (Pasos 3 y 4 del asistente)
            $table->string('business_type', 100)->nullable();
            $table->string('currency', 10)->default('NIO');
            $table->string('plan', 50)->default('basic');
            
            // Configuración del sistema
            $table->string('timezone', 64)->default('America/Managua'); // IANA tz
            $table->decimal('tax_rate', 5, 4)->default(0.1500);         // IVA por defecto (Nic. 15%)
            $table->enum('status', ['active', 'suspended', 'trial'])
                ->default('trial')->index();                        // Estado de cuenta, indexado
                
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
