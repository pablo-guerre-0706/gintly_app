<?php

declare(strict_types=1);

namespace App\Services\Sales;

use App\Enums\FiscalCondition;
use App\Enums\TaxClass;
use App\Exceptions\FiscalConfigurationMissingException;
use App\Models\Product;
use App\Models\TaxRule;
use App\Support\Money;

/**
 * Servicio de dominio fiscal: única autoridad que resuelve la fiscalidad de una
 * línea. Centraliza la selección determinista de la regla y el cálculo del impuesto,
 * de modo que ni controladores ni modelos dispersen condicionales fiscales ni tasas
 * hardcodeadas.
 *
 * Alcance (delimitación funcional explícita, no deuda técnica): UNA tasa resuelta por
 * línea, configurable por clase fiscal y por ámbito (sucursal o general del negocio).
 * No es un motor de impuestos compuestos ni en cascada.
 *
 * Prioridad de resolución:
 *   1. regla activa específica de la sucursal;
 *   2. regla activa general del negocio para la clase;
 *   3. rechazo controlado (FiscalConfigurationMissingException, 422).
 */
final class TaxResolver
{
    /**
     * Resuelve y calcula la fiscalidad de una línea sobre su base gravable (importe de
     * línea ya con descuento de línea aplicado).
     */
    public function resolveForLine(Product $product, int $branchId, string $lineTaxableBase): FiscalSnapshot
    {
        $class     = $product->tax_class;
        $condition = $class->condition();

        if (! $class->requiresRule()) {
            // Tasa cero: gravado a 0 %, la base integra el gravamen.
            // Exento: fuera del gravamen, base 0.
            $base = $condition === FiscalCondition::Exento ? '0.00' : $lineTaxableBase;

            return new FiscalSnapshot($class, $condition, '0.000000', $base, '0.00', null);
        }

        $rule = $this->resolveRule((int) $product->business_id, $class, $branchId);

        if ($rule === null) {
            throw FiscalConfigurationMissingException::forClass($class, $branchId);
        }

        $rate      = (string) $rule->rate;
        $taxAmount = Money::tax($lineTaxableBase, $rate);

        return new FiscalSnapshot($class, $condition, $rate, $lineTaxableBase, $taxAmount, (int) $rule->id);
    }

    /**
     * Regla activa aplicable: la específica de la sucursal tiene prioridad sobre la
     * general del negocio (branch_id NULL). `branch_id IS NULL` ordena la específica
     * (0) antes que la general (1); first() devuelve la de mayor prioridad.
     */
    private function resolveRule(int $businessId, TaxClass $class, int $branchId): ?TaxRule
    {
        return TaxRule::query()
            ->where('business_id', $businessId)
            ->where('tax_class', $class->value)
            ->where('is_active', true)
            ->where(function ($query) use ($branchId): void {
                $query->where('branch_id', $branchId)->orWhereNull('branch_id');
            })
            ->orderByRaw('branch_id IS NULL')
            ->first();
    }
}
