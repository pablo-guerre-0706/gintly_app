<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Unit;

use App\Http\Requests\BaseTenantRequest;
use App\Models\UnitOfMeasure;

final class StoreUnitRequest extends BaseTenantRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', UnitOfMeasure::class) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:50'],

            // Unicidad estricta sobre abbreviation, no sobre name.
            // units_of_measure no tiene softDelete: excludeTrashed=false
            // evita el error 42S22 por columna deleted_at inexistente.
            'abbreviation' => [
                'required',
                'string',
                'max:10',
                $this->tenantUnique('units_of_measure', 'abbreviation', excludeTrashed: false),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required'         => 'El nombre de la unidad es obligatorio.',
            'name.max'              => 'El nombre no puede exceder los 50 caracteres.',
            'abbreviation.required' => 'La abreviatura es obligatoria.',
            'abbreviation.max'      => 'La abreviatura no puede exceder los 10 caracteres.',
            'abbreviation.unique'   => 'Ya existe una unidad con esta abreviatura en el negocio.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name'         => 'nombre',
            'abbreviation' => 'abreviatura',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(array_filter([
            'name'         => is_string($this->name) ? trim($this->name) : null,
            'abbreviation' => is_string($this->abbreviation) ? trim($this->abbreviation) : null,
        ], static fn ($v) => $v !== null));
    }
}

