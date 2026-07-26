<?php

declare(strict_types=1);

namespace App\Enums;


enum ProductType: string
{
    case Simple   = 'simple';
    case Compound = 'compound';
    case Service  = 'service';

    public function label(): string
    {
        return match ($this) {
            self::Simple   => 'Producto simple',
            self::Compound => 'Producto compuesto',
            self::Service  => 'Servicio',
        };
    }

    // Un servicio no tiene existencia física: nunca rastrea inventario.
    // chk_service_no_inventory.
    public function tracksInventoryByNature(): bool
    {
        return $this !== self::Service;
    }

    // Solo un compuesto admite receta (product_recipes.compound_id).
    public function canHaveRecipe(): bool
    {
        return $this === self::Compound;
    }

    // Un servicio no puede ser insumo de una receta: no tiene unidad física que consumir
    public function canBeIngredient(): bool
    {
        return $this !== self::Service;
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
