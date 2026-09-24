<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\TaxRule;

use App\Http\Requests\BaseTenantRequest;
use App\Models\TaxRule;

/**
 * Solo se actualizan la tasa y el estado. La clase y el ámbito (business/tax_class/
 * branch) son inmutables: definen la identidad de la regla. Para cambiar el ámbito se
 * desactiva esta y se crea otra, preservando la trazabilidad.
 */
final class UpdateTaxRuleRequest extends BaseTenantRequest
{
    public function authorize(): bool
    {
        $target = $this->route('taxRule');

        return $target instanceof TaxRule
            && ($this->user()?->can('update', $target) ?? false);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'rate'      => ['sometimes', 'numeric', 'decimal:0,6', 'min:0', 'max:1'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'rate.numeric'      => 'La tasa debe ser un valor numérico.',
            'rate.decimal'      => 'La tasa admite un máximo de seis decimales.',
            'rate.min'          => 'La tasa no puede ser negativa.',
            'rate.max'          => 'La tasa no puede exceder 1 (100 %).',
            'is_active.boolean' => 'El estado debe ser verdadero o falso.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'rate'      => 'tasa',
            'is_active' => 'estado',
        ];
    }
}
