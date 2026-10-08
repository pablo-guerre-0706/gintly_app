<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Billing;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Validation\Rule;

/**
 * Cambio de plan de la MISMA suscripción. El cliente SOLO envía plan y periodicidad (selectores); se rechazan
 * business_id, importes, estados, URLs y claves adicionales. El backend decide ascenso/descenso, precio,
 * variante y programación.
 */
final class PlanChangeRequest extends OwnerBillingRequest
{
    private const ALLOWED = ['plan', 'period'];

    /** @var array<string, mixed> */
    private array $rawBody = [];

    public function rules(): array
    {
        return [
            'plan'   => ['required', 'string', Rule::in(array_keys((array) config('billing.catalog', [])))],
            'period' => ['required', 'string', Rule::in((array) config('billing.periods', []))],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->rawBody = $this->all();
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                foreach (array_keys($this->rawBody) as $key) {
                    if (! in_array($key, self::ALLOWED, true)) {
                        $validator->errors()->add((string) $key, "El campo {$key} no está permitido en el cambio de plan.");
                    }
                }
            },
        ];
    }

    public function messages(): array
    {
        return [
            'plan.required'   => 'Debe indicar el plan destino.',
            'plan.in'         => 'El plan indicado no está disponible.',
            'period.required' => 'Debe indicar la periodicidad (mensual o anual).',
            'period.in'       => 'La periodicidad indicada no es válida.',
        ];
    }
}
