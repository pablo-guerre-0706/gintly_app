<?php

declare(strict_types=1);

namespace App\Enums;

// Estado de la cuenta por pagar (accounts_payable.status).
enum AccountPayableStatus: string
{
    case Pendiente = 'pendiente';
    case Congelada = 'congelada';
    case Parcial   = 'parcial';
    case Pagada    = 'pagada';

    public function label(): string
    {
        return match ($this) {
            self::Pendiente => 'Pendiente',
            self::Congelada => 'Congelada',
            self::Parcial   => 'Pago parcial',
            self::Pagada    => 'Pagada',
        };
    }

    // Una cuenta congelada no admite pagos.
    public function isBlocked(): bool
    {
        return $this === self::Congelada;
    }

    // Estados terminales de deuda saldada.
    public function isSettled(): bool
    {
        return $this === self::Pagada;
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
