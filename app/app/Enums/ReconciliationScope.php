<?php

declare(strict_types=1);

namespace App\Enums;

enum ReconciliationScope: string
{
    case Caja             = 'caja';
    case InventarioBodega = 'inventario_bodega';
    case Compras3Way      = 'compras_3way';
    case Integral         = 'integral';

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_map(static fn (self $s): string => $s->value, self::cases());
    }

    public function label(): string
    {
        return match ($this) {
            self::Caja             => 'Caja',
            self::InventarioBodega => 'Inventario y bodega',
            self::Compras3Way      => 'Compras (3-way)',
            self::Integral         => 'Integral',
        };
    }
}
