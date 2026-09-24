<?php

declare(strict_types=1);

namespace App\Services\Fiscal;

use App\Enums\TaxClass;
use App\Models\TaxRule;
use Illuminate\Support\Facades\DB;

/**
 * Gestión VERSIONADA de reglas fiscales. Una modificación de tasa NO muta la fila
 * en sitio: preserva la regla anterior (para que las líneas históricas que la
 * referencian por sale_items.tax_rule_id conserven su versión) y crea una NUEVA
 * regla activa, todo en una transacción. El candado de motor
 * uniq_active_tax_rule_scope garantiza a lo sumo una activa por (negocio, clase,
 * ámbito); la desactivación previa libera el candado antes de crear la nueva.
 */
final class TaxRuleService
{
    private const RATE_SCALE = 6;

    /**
     * Crea una regla activa nueva (POST). Si el ámbito ya tiene una activa, el
     * candado de motor lanza 1062, que el controlador traduce a 422.
     */
    public function crear(int $businessId, TaxClass $class, ?int $branchId, string $rate): TaxRule
    {
        return DB::transaction(fn (): TaxRule => $this->nueva($businessId, $class, $branchId, $rate, true));
    }

    /**
     * Cambio de tasa VERSIONADO (PUT rate). Si la tasa difiere y la regla está
     * activa: desactiva la anterior y crea una nueva activa de la misma clase/ámbito.
     * Si la regla ya está inactiva (histórica), corrige su tasa en sitio sin versionar
     * (no afecta a ninguna activa). Si la tasa no cambia, es idempotente.
     */
    public function actualizarTasa(TaxRule $rule, string $rate): TaxRule
    {
        return DB::transaction(fn (): TaxRule => $this->versionarTasa((int) $rule->getKey(), $rate));
    }

    /** Baja lógica / reactivación en sitio (no versiona: no cambia la tasa). */
    public function setActivo(TaxRule $rule, bool $active): TaxRule
    {
        return DB::transaction(function () use ($rule, $active): TaxRule {
            $current = TaxRule::withoutGlobalScopes()->whereKey($rule->getKey())->lockForUpdate()->firstOrFail();
            $current->update(['is_active' => $active]);

            return $current->refresh();
        });
    }

    /**
     * Traducción transaccional de business.tax_rate → regla ESTÁNDAR GENERAL del
     * negocio (única fuente operativa). Versiona la activa si existe; la crea si no.
     */
    public function fijarTasaEstandarGeneral(int $businessId, string $rate): TaxRule
    {
        return DB::transaction(function () use ($businessId, $rate): TaxRule {
            $current = TaxRule::withoutGlobalScopes()
                ->where('business_id', $businessId)
                ->where('tax_class', TaxClass::Standard->value)
                ->whereNull('branch_id')
                ->where('is_active', true)
                ->lockForUpdate()
                ->first();

            return $current === null
                ? $this->nueva($businessId, TaxClass::Standard, null, $rate, true)
                : $this->versionarTasa((int) $current->getKey(), $rate);
        });
    }

    /** Núcleo de versionado. DEBE ejecutarse dentro de una transacción. */
    private function versionarTasa(int $ruleId, string $rate): TaxRule
    {
        $current = TaxRule::withoutGlobalScopes()->whereKey($ruleId)->lockForUpdate()->firstOrFail();

        if (bccomp($rate, (string) $current->rate, self::RATE_SCALE) === 0) {
            return $current; // idempotente
        }

        if (! $current->is_active) {
            $current->update(['rate' => $rate]); // histórica: corrección en sitio

            return $current->refresh();
        }

        $current->update(['is_active' => false]); // preserva la fila anterior

        return $this->nueva((int) $current->business_id, $current->tax_class, $current->branch_id, $rate, true);
    }

    private function nueva(int $businessId, TaxClass $class, ?int $branchId, string $rate, bool $active): TaxRule
    {
        $rule = new TaxRule();
        $rule->forceFill([
            'business_id' => $businessId,
            'tax_class'   => $class->value,
            'branch_id'   => $branchId,
            'rate'        => $rate,
            'is_active'   => $active,
        ])->save();

        return $rule->refresh();
    }
}
