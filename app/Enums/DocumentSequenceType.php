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
    case SALE = 'sale';
    case SALE_RETURN = 'sale_return'; 


    public function label(): string
    {
        return match ($this) {
            self::Invoice    => 'Factura',
            self::CreditNote => 'Nota de crédito',
        };
    }

    public function defaultPrefix(): string
    {
        return match ($this) {
            self::Invoice    => 'F-',
            self::CreditNote => 'NC-',
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
