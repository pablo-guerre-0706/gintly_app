<?php

declare(strict_types=1);

namespace App\Enums;


//Causa de un ajuste de inventario (inventory_adjustments.type).
enum InventoryAdjustmentType: string
{
    case Merma      = 'merma';       // pérdida: resta stock
    case Sobrante   = 'sobrante';    // excedente: suma stock
    case Correccion = 'correccion';  // corrección: el signo depende del conteo

    public function label(): string
    {
        return match ($this) {
            self::Merma      => 'Merma',
            self::Sobrante   => 'Sobrante',
            self::Correccion => 'Corrección',
        };
    }

    // Signo con que el ajuste directo afecta el stock.
    public function directionFactor(): int
    {
        return match ($this) {
            self::Merma      => -1,
            self::Sobrante   => 1,
            self::Correccion => throw new \LogicException(
                'La corrección deriva su signo de la diferencia del conteo, no del tipo.'
            ),
        };
    }

    // True si el tipo (merma/sobrante) lleva un signo fijo. La corrección no.
    public function hasFixedDirection(): bool
    {
        return $this !== self::Correccion;
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
