<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Brand;

use App\Http\Requests\BaseTenantRequest;
use App\Models\Brand;

final class StoreBrandRequest extends BaseTenantRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Brand::class) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:120',
                $this->tenantUnique('brands', 'name', excludeTrashed: true),
            ],

            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required'     => 'El nombre de la marca es obligatorio.',
            'name.max'          => 'El nombre no puede exceder los 120 caracteres.',
            'name.unique'       => 'Ya existe una marca activa con este nombre en el negocio.',
            'is_active.boolean' => 'El estado de la marca debe ser verdadero o falso.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name'      => 'nombre',
            'is_active' => 'estado',
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->name)) {
            $this->merge(['name' => trim($this->name)]);
        }
    }
}
