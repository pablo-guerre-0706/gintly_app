<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Clase fiscal explícita del producto (products.tax_class) y congelada por línea.
 *
 * Reemplaza al booleano is_taxable como ÚNICA fuente de clasificación fiscal:
 * distingue estándar, reducida/especial, tasa cero y exenta —escenarios que un
 * booleano no puede representar—. La TASA aplicable la resuelve una regla fiscal
 * persistida (tax_rules); esta clase solo describe la naturaleza fiscal.
 */
enum TaxClass: string
{
    case Standard  = 'standard';   // tasa general del negocio
    case Reduced   = 'reduced';    // tasa reducida o especial
    case ZeroRated = 'zero_rated'; // gravado a 0 % (integra base gravable)
    case Exempt    = 'exempt';     // exento (fuera de la base gravable)

    public function label(): string
    {
        return match ($this) {
            self::Standard  => 'Tasa general',
            self::Reduced   => 'Tasa reducida',
            self::ZeroRated => 'Tasa cero',
            self::Exempt    => 'Exento',
        };
    }

    /**
     * Condición fiscal efectiva derivada de la clase.
     */
    public function condition(): FiscalCondition
    {
        return match ($this) {
            self::Standard, self::Reduced => FiscalCondition::Gravado,
            self::ZeroRated               => FiscalCondition::TasaCero,
            self::Exempt                  => FiscalCondition::Exento,
        };
    }

    /**
     * ¿Requiere resolver una tasa desde tax_rules? Solo las clases gravadas
     * (estándar/reducida). Tasa cero y exento tienen tasa 0 por definición y no
     * consultan reglas; si no hay regla aplicable para una clase gravada, la venta
     * se rechaza de forma controlada.
     */
    public function requiresRule(): bool
    {
        return $this->condition() === FiscalCondition::Gravado;
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
