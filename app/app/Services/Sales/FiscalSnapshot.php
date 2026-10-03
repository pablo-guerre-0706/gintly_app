<?php

declare(strict_types=1);

namespace App\Services\Sales;

use App\Enums\FiscalCondition;
use App\Enums\TaxClass;

/**
 * Resultado inmutable de la resolución fiscal de una línea. Se congela tal cual en
 * sale_items; la factura suma sus importes sin volver a consultar la configuración.
 */
final class FiscalSnapshot
{
    public function __construct(
        public readonly TaxClass $taxClass,
        public readonly FiscalCondition $condition,
        public readonly string $rate,        // fracción, 6 decimales
        public readonly string $taxableBase, // 2 decimales
        public readonly string $taxAmount,   // 2 decimales, ya redondeado
        public readonly ?int $taxRuleId,
    ) {
    }

    /**
     * Atributos congelables en la línea de venta.
     *
     * @return array<string, mixed>
     */
    public function toItemAttributes(): array
    {
        return [
            'is_taxable'       => $this->condition->isSubjectToTax(),
            'tax_class'        => $this->taxClass->value,
            'fiscal_condition' => $this->condition->value,
            'tax_rate'         => $this->rate,
            'taxable_base'     => $this->taxableBase,
            'tax_amount'       => $this->taxAmount,
            'tax_rule_id'      => $this->taxRuleId,
        ];
    }
}
