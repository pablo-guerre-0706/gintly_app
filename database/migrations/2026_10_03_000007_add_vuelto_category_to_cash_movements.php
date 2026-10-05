<?php

declare(strict_types=1);

use App\Enums\CashMovementCategory;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * MOD-06 · Nueva categoría de movimiento de caja 'vuelto' (egreso) para el CAMBIO entregado al cliente en
 * un cobro en efectivo con doble moneda. ADITIVA e IDEMPOTENTE: amplía el ENUM category sin tocar datos.
 *
 * El vuelto es un egreso real del cajón: cuenta en el esperado (reduce el efectivo de SU moneda) y lleva
 * su propia moneda/importe nativo/tasa snapshot/equivalente NIO, igual que cualquier movimiento.
 */
return new class extends Migration
{
    public function up(): void
    {
        $columnType = DB::selectOne(
            'SELECT COLUMN_TYPE AS t FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            ['cash_movements', 'category']
        );

        if ($columnType !== null && str_contains((string) $columnType->t, "'vuelto'")) {
            return; // ya está ampliado.
        }

        $values = "'".implode("','", CashMovementCategory::values())."'";
        DB::statement("ALTER TABLE cash_movements MODIFY COLUMN category ENUM({$values}) NOT NULL");
    }

    public function down(): void
    {
        // Reversa best-effort: restaura el ENUM sin 'vuelto'. Solo seguro si no hay filas 'vuelto'.
        $legacy = array_values(array_filter(
            CashMovementCategory::values(),
            static fn (string $v): bool => $v !== CashMovementCategory::Vuelto->value,
        ));

        $values = "'".implode("','", $legacy)."'";

        try {
            DB::statement("ALTER TABLE cash_movements MODIFY COLUMN category ENUM({$values}) NOT NULL");
        } catch (\Throwable) {
            // hay movimientos 'vuelto': no se puede estrechar el ENUM sin perder evidencia.
        }
    }
};
