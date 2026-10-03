<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\TaxRule;

use App\Enums\TaxClass;
use App\Http\Requests\BaseTenantRequest;
use App\Models\TaxRule;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class StoreTaxRuleRequest extends BaseTenantRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', TaxRule::class) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'tax_class' => ['required', Rule::enum(TaxClass::class)],
            // NULL = regla general del negocio; con valor = regla específica de sucursal.
            'branch_id' => ['nullable', 'integer', $this->tenantExists('branches')],
            // Tasa fraccional [0,1] con hasta 6 decimales (0.150000 = 15 %).
            'rate'      => ['required', 'numeric', 'decimal:0,6', 'min:0', 'max:1'],
        ];
    }

    /**
     * No puede coexistir más de una regla ACTIVA para el mismo (clase, ámbito). El
     * candado de motor lo garantiza; aquí se anticipa con un mensaje claro.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $taxClass = $this->input('tax_class');
                if (! is_string($taxClass) || $validator->errors()->isNotEmpty()) {
                    return;
                }

                $exists = TaxRule::query()
                    ->where('business_id', $this->businessId())
                    ->where('tax_class', $taxClass)
                    ->where('is_active', true)
                    ->when(
                        $this->input('branch_id') === null,
                        fn ($q) => $q->whereNull('branch_id'),
                        fn ($q) => $q->where('branch_id', (int) $this->input('branch_id')),
                    )
                    ->exists();

                if ($exists) {
                    $validator->errors()->add(
                        'tax_class',
                        'Ya existe una regla fiscal activa para esta clase y ámbito. Actualícela o desactívela antes de crear otra.'
                    );
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'tax_class.required' => 'La clase fiscal es obligatoria.',
            'tax_class.enum'     => 'La clase fiscal debe ser tasa general, reducida, tasa cero o exento.',
            'branch_id.exists'   => 'La sucursal indicada no existe o no pertenece a su negocio.',
            'rate.required'      => 'La tasa es obligatoria.',
            'rate.numeric'       => 'La tasa debe ser un valor numérico.',
            'rate.decimal'       => 'La tasa admite un máximo de seis decimales.',
            'rate.min'           => 'La tasa no puede ser negativa.',
            'rate.max'           => 'La tasa no puede exceder 1 (100 %).',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'tax_class' => 'clase fiscal',
            'branch_id' => 'sucursal',
            'rate'      => 'tasa',
        ];
    }
}
