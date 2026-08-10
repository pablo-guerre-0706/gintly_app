<?php

declare(strict_types=1);

namespace App\Enums;

enum AnomalyThresholdType: string
{
    case Monto      = 'monto';
    case Porcentaje = 'porcentaje';
    case Cantidad   = 'cantidad';
    case Tiempo     = 'tiempo';

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_map(static fn (self $t): string => $t->value, self::cases());
    }

    public function label(): string
    {
        return match ($this) {
            self::Monto      => 'Monto',
            self::Porcentaje => 'Porcentaje',
            self::Cantidad   => 'Cantidad',
            self::Tiempo     => 'Tiempo',
        };
    }
}