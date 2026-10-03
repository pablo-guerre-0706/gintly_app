<?php

declare(strict_types=1);

use App\Enums\OperativeProfile;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 3 · Perfiles operativos de ROL-03 (persistencia normalizada y auditable).
 *
 * Un usuario ROL-03 puede tener uno o varios perfiles combinables (cajero/facturador/bodeguero/
 * despachador). Unicidad por (user, profile). Aislado por negocio (business_id) y auditable
 * (assigned_by, timestamps). Compatible con Spatie teams (el rol sigue siendo ROL-03; el perfil es
 * la capa fina). No se asignan perfiles automáticamente: un ROL-03 sin filas aquí queda bloqueado
 * operativamente (opción B, mínimo privilegio).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('user_operative_profiles')) {
            return;
        }

        Schema::create('user_operative_profiles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->enum('profile', OperativeProfile::values());
            // No-repudio: quién asignó el perfil. RESTRICT preserva la atribución.
            $table->foreignId('assigned_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();

            // Un perfil no se repite por usuario.
            $table->unique(['user_id', 'profile'], 'uniq_user_operative_profile');
            $table->index('business_id', 'idx_uop_business');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_operative_profiles');
    }
};
