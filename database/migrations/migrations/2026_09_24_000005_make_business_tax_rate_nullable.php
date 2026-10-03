<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * MOD-07 · Elimina la PRESUNCIÓN FISCAL universal.
 *
 * businesses.tax_rate tenía DEFAULT 0.1500 (15 % de Nicaragua) y NOT NULL, de modo
 * que TODO negocio nuevo heredaba silenciosamente esa tasa. Se vuelve NULLABLE y sin
 * DEFAULT: un negocio nuevo SIN tasa explícita no recibe 15 % por omisión; su regla
 * estándar solo se siembra si declara una tasa (ver BusinessObserver).
 *
 * MODIFY no altera los datos existentes: las tasas de negocios ya creados y las
 * reglas ya migradas se conservan intactas. MySQL-only (ALTER ... MODIFY).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE businesses MODIFY tax_rate DECIMAL(5,4) NULL DEFAULT NULL');
    }

    public function down(): void
    {
        // Reversa conservadora: rellena los null antes de restaurar NOT NULL + DEFAULT.
        DB::statement('UPDATE businesses SET tax_rate = 0.1500 WHERE tax_rate IS NULL');
        DB::statement('ALTER TABLE businesses MODIFY tax_rate DECIMAL(5,4) NOT NULL DEFAULT 0.1500');
    }
};
