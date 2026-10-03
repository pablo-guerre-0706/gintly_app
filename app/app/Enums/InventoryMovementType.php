<?php

declare(strict_types=1);

namespace App\Enums;


// Tipo físico de asiento en el kardex (inventory_movements.type). Solo movimiento fisico
enum InventoryMovementType: string
{
    case Entrada  = 'entrada';
    case Salida   = 'salida';
    case Ajuste   = 'ajuste';
    case Traspaso = 'traspaso';

    public function label(): string
    {
        return match ($this) {
            self::Entrada  => 'Entrada',
            self::Salida   => 'Salida',
            self::Ajuste   => 'Ajuste',
            self::Traspaso => 'Traspaso',
        };
    }

    
    public function isInbound(): bool
    {
        return $this === self::Entrada;
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
