<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Tipo de documento con folio secuencial fiscal (document_sequences.document_type).
 */
enum DocumentSequenceType: string
{
    case Invoice     = 'invoice';
    case CreditNote  = 'credit_note';
    case SalesReturn = 'sales_return'; // <--- CASO AÑADIDO PARA EVITAR EL ERROR

    public function label(): string
    {
        return match ($this) {
            self::Invoice     => 'Factura',
            self::CreditNote  => 'Nota de crédito',
            self::SalesReturn => 'Devolución de venta',
        };
    }

    public function defaultPrefix(): string
    {
        return match ($this) {
            self::Invoice     => 'F-',
            self::CreditNote  => 'NC-',
            self::SalesReturn => 'DV-',
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