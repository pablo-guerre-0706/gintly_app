<?php

namespace App\Enums;

enum InventoryMovementType: string
{
    case Entrada  = 'entrada';
    case Salida   = 'salida';
    case Ajuste   = 'ajuste';
    case Traspaso = 'traspaso';

    /** +1 suma stock, -1 lo resta (ajuste/traspaso los decide el Service). */
    public function isInbound(): bool
    {
        return $this === self::Entrada;
    }

    public function label(): string
    {
        return match ($this) {
            self::Entrada  => 'Entrada',
            self::Salida   => 'Salida',
            self::Ajuste   => 'Ajuste',
            self::Traspaso => 'Traspaso',
        };
    }
}
