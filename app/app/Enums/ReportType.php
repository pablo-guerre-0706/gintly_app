<?php

declare(strict_types=1);

namespace App\Enums;

enum ReportType: string
{
    case Ventas      = 'ventas';
    case Cartera     = 'cartera';
    case Inventario  = 'inventario';
    case Caja        = 'caja';
    case Consolidado = 'consolidado';

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_map(static fn (self $t): string => $t->value, self::cases());
    }

    public function label(): string
    {
        return match ($this) {
            self::Ventas      => 'Ventas',
            self::Cartera     => 'Cartera',
            self::Inventario  => 'Inventario',
            self::Caja        => 'Caja',
            self::Consolidado => 'Consolidado',
        };
    }
}
