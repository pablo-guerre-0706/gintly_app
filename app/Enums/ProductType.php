<?php

namespace App\Enums;

enum ProductType: string
{
    case Simple   = 'simple';    // se compra y vende tal cual
    case Compound = 'compound';  // se arma de insumos (platillo/combo)
    case Service  = 'service';   // sin existencia física

    /** Regla de dominio: solo lo físico rastrea inventario. */
    public function tracksInventory(): bool
    {
        return $this !== self::Service;
    }

    public function label(): string
    {
        return match ($this) {
            self::Simple   => 'Producto simple',
            self::Compound => 'Producto compuesto',
            self::Service  => 'Servicio',
        };
    }
}