<?php

declare(strict_types=1);

namespace App\Enums;


// Ciclo de vida de un traspaso entre bodegas (stock_transfers.status).
enum StockTransferStatus: string
{
    case Pendiente   = 'pendiente';
    case Completado  = 'completado';
    case Cancelado   = 'cancelado';

    public function label(): string
    {
        return match ($this) {
            self::Pendiente  => 'Pendiente',
            self::Completado => 'Completado',
            self::Cancelado  => 'Cancelado',
        };
    }

    // Un traspaso se completa o cancela desde 'pendiente'.
    public function canTransition(): bool
    {
        return $this === self::Pendiente;
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}

