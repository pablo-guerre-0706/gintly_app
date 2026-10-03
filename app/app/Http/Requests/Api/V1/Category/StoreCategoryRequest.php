<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Category;

use App\Http\Requests\BaseTenantRequest;
use App\Models\Category;

final class StoreCategoryRequest extends BaseTenantRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Category::class) ?? false;
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
                // Candado parcial name_lock (H-14): excludeTrashed alineado
                // con uniq_active_category_name del motor.
                $this->tenantUnique('categories', 'name', excludeTrashed: true),
            ],

            // parent_id: solo forma y pertenencia. El ciclo indirecto lo valida
            // CategoryService por DFS (H-17). La auto-referencia directa no
            // aplica en Store: el id aún no existe.
            'parent_id' => [
                'nullable',
                'integer',
                $this->tenantExists('categories'),
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
            'name.required'     => 'El nombre de la categoría es obligatorio.',
            'name.max'          => 'El nombre no puede exceder los 120 caracteres.',
            'name.unique'       => 'Ya existe una categoría activa con este nombre en el negocio.',
            'parent_id.integer' => 'La categoría padre seleccionada no es válida.',
            'parent_id.exists'  => 'La categoría padre seleccionada no existe o no pertenece a su negocio.',
            'is_active.boolean' => 'El estado de la categoría debe ser verdadero o falso.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name'      => 'nombre',
            'parent_id' => 'categoría padre',
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
