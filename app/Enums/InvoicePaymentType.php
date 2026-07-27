<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Modalidad de pago de la factura (invoices.payment_type).
 */
enum InvoicePaymentType: string
{
    case Contado = 'contado';
    case Credito = 'credito';

    public function label(): string
    {
        return match ($this) {
            self::Contado => 'Contado',
            self::Credito => 'Crédito',
        };
    }

    // El contado exige que la suma de pagos cubra el total (ERR-07).
    // El crédito puede emitirse sin pago inmediato: la deuda va a CxC.
    public function requiresFullPayment(): bool
    {
        return $this === self::Contado;
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
