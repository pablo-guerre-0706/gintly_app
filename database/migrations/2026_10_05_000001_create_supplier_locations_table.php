<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * MOD-04 · Ubicaciones de proveedor para el mapa. ADITIVA e IDEMPOTENTE.
 *
 * Una o varias direcciones por proveedor. Cada ubicación guarda la dirección, coordenadas (nullable hasta
 * geocodificar/confirmar), procedencia (`geocode_source`), identificador externo, calidad, y las fechas de
 * geocodificación y confirmación con su confirmador. El mapa SOLO expone ubicaciones CONFIRMADAS
 * (confirmed_at NOT NULL). Invariantes de motor: coordenadas en rango, par (lat,lng) completo o nulo, y una
 * sola ubicación PRINCIPAL por proveedor (candado parcial sobre columna generada).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('supplier_locations')) {
            return;
        }

        Schema::create('supplier_locations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained('suppliers')->cascadeOnDelete();
            $table->string('address', 255);
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('geocode_source', 20)->nullable();   // manual / geocoded / external
            $table->string('external_id', 120)->nullable();      // id del proveedor externo del mapa
            $table->string('quality', 40)->nullable();           // precisión reportada por el geocoder
            $table->timestamp('geocoded_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->boolean('is_primary')->default(false);
            $table->timestamps();

            $table->index(['business_id', 'supplier_id'], 'idx_supplier_locations_supplier');
        });

        // Coordenadas en rango y par completo o nulo (no media coordenada).
        DB::statement('ALTER TABLE supplier_locations ADD CONSTRAINT chk_sl_lat_range CHECK (latitude IS NULL OR (latitude >= -90 AND latitude <= 90))');
        DB::statement('ALTER TABLE supplier_locations ADD CONSTRAINT chk_sl_lng_range CHECK (longitude IS NULL OR (longitude >= -180 AND longitude <= 180))');
        DB::statement('ALTER TABLE supplier_locations ADD CONSTRAINT chk_sl_coords_pair CHECK ((latitude IS NULL) = (longitude IS NULL))');

        // Una sola ubicación PRINCIPAL por proveedor: candado parcial sobre columna generada (NULL no colisiona).
        DB::statement("
            ALTER TABLE supplier_locations
                ADD COLUMN primary_lock BIGINT UNSIGNED
                    GENERATED ALWAYS AS (CASE WHEN is_primary = 1 THEN supplier_id END) VIRTUAL,
                ADD UNIQUE KEY uniq_supplier_primary_location (primary_lock)
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_locations');
    }
};
