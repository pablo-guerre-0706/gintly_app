<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Ciclo de vida de la venta (sales.status).
 */
enum SaleStatus: string
{
    case Abierta    = 'abierta';
    case Confirmada = 'confirmada';
    case Facturada  = 'facturada';
    case Anulada    = 'anulada';

    public function label(): string
    {
        return match ($this) {
            self::Abierta    => 'Abierta',
            self::Confirmada => 'Confirmada',
            self::Facturada  => 'Facturada',
            self::Anulada    => 'Anulada',
        };
    }

    // Los ítems solo se editan mientras la venta está abierta.
    public function canEditItems(): bool
    {
        return $this === self::Abierta;
    }

    // Solo una venta confirmada puede facturarse.
    public function canInvoice(): bool
    {
        return $this === self::Confirmada;
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
