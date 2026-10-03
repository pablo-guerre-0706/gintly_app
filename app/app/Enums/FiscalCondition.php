<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Condición fiscal efectiva de una línea de venta (congelada en sale_items).
 *
 * Distingue explícitamente tasa cero de exento: ambos producen impuesto 0, pero
 * el gravado a tasa cero SÍ integra la base gravable del sistema fiscal, mientras
 * que el exento queda FUERA de la base. Esa diferencia es relevante para reportes
 * y para MOD-10 (devoluciones/notas de crédito) sin recalcular nada.
 */
enum FiscalCondition: string
{
    case Gravado  = 'gravado';
    case TasaCero = 'tasa_cero';
    case Exento   = 'exento';

    public function label(): string
    {
        return match ($this) {
            self::Gravado  => 'Gravado',
            self::TasaCero => 'Tasa cero',
            self::Exento   => 'Exento',
        };
    }

    /**
     * ¿La línea está SUJETA al impuesto (aunque la tasa sea 0)? El exento no lo está;
     * gravado y tasa cero sí. Es la fuente del `is_taxable` derivado.
     */
    public function isSubjectToTax(): bool
    {
        return $this !== self::Exento;
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
