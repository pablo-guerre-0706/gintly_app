<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Estado de cobro (invoices.payment_status). Mutable: con los abonos, diferencia es inmutable.
 */
enum InvoicePaymentStatus: string
{
    case Pagada     = 'pagada';
    case Parcial    = 'parcial';
    case Pendiente  = 'pendiente';

    public function label(): string
    {
        return match ($this) {
            self::Pagada    => 'Pagada',
            self::Parcial   => 'Parcial',
            self::Pendiente => 'Pendiente',
        };
    }

    // Deriva el estado de cobro comparando lo pagado con el total (bcmath e2).
    public static function fromAmounts(string $paid, string $total): self
    {
        if (bccomp($paid, '0', 2) <= 0) {
            return self::Pendiente;
        }

        return bccomp($paid, $total, 2) >= 0 ? self::Pagada : self::Parcial;
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
