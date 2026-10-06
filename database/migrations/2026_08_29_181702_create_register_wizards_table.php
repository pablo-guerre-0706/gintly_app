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

            // Paso 1: Configuración de tu Perfil
            $table->string('nombre', 100)->nullable();
            $table->string('apellido', 100)->nullable();
            $table->string('correo', 150)->nullable();
            $table->string('telefono', 20)->nullable();
            $table->string('codigo_pais', 10)->nullable();
            $table->string('password', 255)->nullable();

            // Paso 2: Configura el espacio de tú negocio
            $table->string('nombre_tienda', 150)->nullable();
            $table->string('pais_region', 100)->nullable();
            $table->string('ciudad', 100)->nullable();
            $table->string('codigo_postal', 15)->nullable();
            $table->string('direccion', 255)->nullable();
            $table->string('correo_tienda', 150)->nullable();
            $table->string('numero_convencional', 20)->nullable();
            $table->unsignedSmallInteger('numero_sucursales')->default(1);
            $table->string('ruc', 50)->nullable();

            // Paso 3: Tipo de negocio
            $table->string('tipo_negocio', 50)->nullable();

            // Paso 4: Preferencias regionales
            $table->string('zona_horaria', 100)->nullable();
            $table->string('moneda', 10)->nullable();
            $table->date('fecha_creacion')->nullable();

            // Paso 5: Creación de usuarios / Empleados (Opcional)
            $table->string('empleado_nombre', 100)->nullable();
            $table->string('empleado_apellido', 100)->nullable();
            $table->string('empleado_correo', 150)->nullable();
            $table->string('empleado_telefono', 20)->nullable();
            $table->string('empleado_rol', 50)->nullable();

            // Paso 6: Suscripción, Planes y Facturación
            $table->string('plan', 50)->nullable();
            $table->string('ciclo', 20)->nullable(); // monthly o annual
            $table->string('billing_nombre', 150)->nullable();
            $table->string('billing_razon', 150)->nullable();
            $table->string('billing_correo', 150)->nullable();
            $table->string('metodo_pago', 30)->nullable(); // tarjeta o transferencia
            $table->string('numero_tarjeta', 30)->nullable();
            $table->string('nombre_tarjeta', 150)->nullable();
            $table->string('vencimiento', 10)->nullable();
            $table->string('cvc', 10)->nullable();
            $table->string('referencia_transferencia', 100)->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('register_wizards');
    }
};