<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Estado fiscal (invoices.status). Se emite o se anula. Nunca se borra ni se edita.
 */
enum InvoiceStatus: string
{
    case Emitida = 'emitida';
    case Anulada = 'anulada';

    public function label(): string
    {
        return match ($this) {
            self::Emitida => 'Emitida',
            self::Anulada => 'Anulada',
        };
    }

    public function isVoided(): bool
    {
        return $this === self::Anulada;
    }

    // Solo una factura emitida puede anularse.
    public function canVoid(): bool
    {
        return $this === self::Emitida;
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}

