<?php

declare(strict_types=1);

namespace App\Http\Requests\Business;

use App\Http\Requests\BaseTenantRequest;
use Illuminate\Validation\Rule;

/**
 * H-07. `status` y `plan` describen el contrato SaaS, no la operación del
 * negocio. Permitir que ROL-01 —que es el cliente— se asigne status='active'
 * anularía cualquier suspensión por impago. Son potestad de ROL-SYS y no
 * figuran en este request.
 */
final class UpdateBusinessRequest extends BaseTenantRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->user()->business) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:150'],

            // decimal(5,4). Fracción, no porcentaje: 0.1500 = 15 %.
            // `decimal:0,4` cuenta decimales; `max` acota el rango. Son
            // controles ortogonales: sin el primero, 0.15001 pasaría y MySQL
            // lo redondearía en silencio, desplazando el IVA de todas las
            // facturas siguientes.
            'tax_rate' => ['sometimes', 'required', 'numeric', 'decimal:0,4', 'min:0', 'max:0.9999'],

            // Rule::timezone() valida contra la base IANA real; una lista fija
            // en `in:` se desactualiza con cada revisión de husos.
            'timezone' => ['sometimes', 'required', 'string', 'max:64', Rule::timezone()],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required'     => 'La razón social no puede quedar vacía.',
            'name.max'          => 'La razón social no puede exceder los 150 caracteres.',
            'tax_rate.required' => 'La tasa impositiva no puede quedar vacía.',
            'tax_rate.numeric'  => 'La tasa impositiva debe ser un valor numérico.',
            'tax_rate.decimal'  => 'La tasa impositiva admite un máximo de cuatro decimales.',
            'tax_rate.min'      => 'La tasa impositiva no puede ser negativa.',
            'tax_rate.max'      => 'La tasa impositiva debe expresarse como fracción menor que 1 (por ejemplo, 0.15 para el 15 %).',
            'timezone.required' => 'La zona horaria no puede quedar vacía.',
            'timezone.max'      => 'La zona horaria no puede exceder los 64 caracteres.',
            'timezone.timezone' => 'La zona horaria debe ser un identificador IANA válido (por ejemplo, America/Managua).',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name'     => 'razón social',
            'tax_rate' => 'tasa impositiva',
            'timezone' => 'zona horaria',
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->name)) {
            $this->merge(['name' => trim($this->name)]);
        }
    }
}
