<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Tipo de documento con folio secuencial fiscal (document_sequences.document_type).
 */
enum DocumentSequenceType: string
{
    case Invoice    = 'invoice';
    case CreditNote = 'credit_note';
    case Sale = 'sale';
    case Sales_Return = 'sales_return'; 


    public function label(): string
    {
        return match ($this) {
            self::Invoice    => 'Factura',
            self::CreditNote => 'Nota de crédito',
            self::Sale        => 'Venta',
            self::Sales_Return => 'Nota de devolución'
        };
    }

    public function defaultPrefix(): string
    {
        return match ($this) {
            self::Invoice    => 'F-',
            self::CreditNote => 'NC-',
            self::Sale        => 'V-',
            self::Sales_Return => 'DEV-',
        };
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
