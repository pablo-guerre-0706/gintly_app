<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Infraestructura de IDEMPOTENCIA del registro público canónico (POST /api/v1/auth/register).
 *
 * Progresiva y aditiva. Una fila se escribe SOLO en un alta exitosa, dentro de la MISMA transacción
 * exterior de creación. La clave (uuid) con índice UNIQUE es el árbitro de concurrencia: dos solicitudes
 * con la misma clave compiten por este índice; el perdedor hace rollback integral y resuelve al ganador
 * mediante una lectura nueva. NO usa business_id como tenant scope (se consulta antes de existir sesión):
 * el modelo RegistrationRequest NO aplica BelongsToBusiness.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('registration_requests')) {
            return; // idempotente
        }

        Schema::create('registration_requests', function (Blueprint $table): void {
            $table->id();
            // Clave de idempotencia canónica (Idempotency-Key). CHAR(36), UNIQUE = árbitro de concurrencia.
            $table->char('uuid', 36);
            // HMAC-SHA256 hex (longitud fija 64) de la representación canónica del payload (incluye contraseña, NO se persiste).
            $table->char('fingerprint', 64);
            // Negocio creado por este registro (se fija dentro de la transacción de alta).
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            // Resultado público persistido: SOLO lo que devuelve el endpoint.
            $table->string('business_slug', 160);
            $table->string('owner_email', 180);
            $table->timestamps();

            $table->unique('uuid', 'uniq_registration_request_uuid');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('registration_requests');
    }
};
