<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Unit;

use App\Http\Requests\BaseTenantRequest;
use App\Models\UnitOfMeasure;

final class UpdateUnitRequest extends BaseTenantRequest
{
    public function authorize(): bool
    {
        $target = $this->route('unit');

        return $this->user()?->can('update', $target instanceof UnitOfMeasure ? $target : UnitOfMeasure::class) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:50'],

            'abbreviation' => [
                'sometimes',
                'required',
                'string',
                'max:10',
                $this->tenantUnique('units_of_measure', 'abbreviation', excludeTrashed: false)
                    ->ignore($this->routeId('unit')),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required'         => 'El nombre de la unidad no puede quedar vacío.',
            'name.max'              => 'El nombre no puede exceder los 50 caracteres.',
            'abbreviation.required' => 'La abreviatura no puede quedar vacía.',
            'abbreviation.max'      => 'La abreviatura no puede exceder los 10 caracteres.',
            'abbreviation.unique'   => 'Ya existe otra unidad con esta abreviatura en el negocio.',
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

