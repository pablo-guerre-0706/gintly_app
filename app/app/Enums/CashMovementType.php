<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Dirección del movimiento de caja (cash_movements.type).
 */
enum CashMovementType: string
{
    case Ingreso = 'ingreso';
    case Egreso  = 'egreso';

    public function label(): string
    {
        return match ($this) {
            self::Ingreso => 'Ingreso',
            self::Egreso  => 'Egreso',
        };
    }

    // Factor con signo para acumular en el esperado: +1 ingreso, −1 egreso.
    public function signedFactor(): int
    {
        return match ($this) {
            self::Ingreso => 1,
            self::Egreso  => -1,
        };
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
