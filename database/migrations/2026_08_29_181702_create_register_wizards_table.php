<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('register_wizards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('cascade');

            // Paso 1: Perfil inicial
            $table->string('nombres', 100)->nullable();
            $table->string('apellidos', 100)->nullable();
            $table->string('email', 150)->nullable();

            // Paso 2: Información de la tienda
            $table->string('nombre_tienda', 150)->nullable();
            $table->string('pais', 100)->nullable();
            $table->string('ciudad', 100)->nullable();
            $table->string('codigo_postal', 15)->nullable();
            $table->string('direccion', 255)->nullable();
            $table->string('email_tienda', 150)->nullable();
            $table->string('telefono_tienda', 20)->nullable();
            $table->unsignedSmallInteger('numero_sucursales')->default(1);
            $table->string('ruc_identificacion', 50)->nullable();

            // Paso 3: Tipo de negocio
            $table->string('tipo_negocio', 50)->nullable();

            // Pasos 4 y 5: Datos del empleado
            $table->string('empleado_nombres', 100)->nullable();
            $table->string('empleado_apellidos', 100)->nullable();
            $table->string('empleado_email', 150)->nullable();
            $table->string('empleado_telefono', 20)->nullable();
            $table->string('empleado_rol', 50)->nullable();

            // Paso 7: Plan y suscripción
            $table->string('plan_seleccionado', 50)->nullable();
            $table->enum('frecuencia_pago', ['mensual', 'anual'])->default('mensual');

            // Paso 8: Facturación y Pago
            $table->string('titular_razon_social', 150)->nullable();
            $table->string('email_facturacion', 150)->nullable();
            $table->enum('metodo_pago', ['tarjeta', 'transferencia'])->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('register_wizards');
    }
};