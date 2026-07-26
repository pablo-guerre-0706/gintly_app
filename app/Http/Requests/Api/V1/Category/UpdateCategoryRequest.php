<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Category;

use App\Http\Requests\BaseTenantRequest;
use App\Models\Category;
use Illuminate\Validation\Validator;

final class UpdateCategoryRequest extends BaseTenantRequest
{
    public function authorize(): bool
    {
        $target = $this->route('category');

        return $this->user()?->can('update', $target instanceof Category ? $target : Category::class) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:120',
                $this->tenantUnique('categories', 'name', excludeTrashed: true)
                    ->ignore($this->routeId('category')),
            ],

            'parent_id' => [
                'sometimes',
                'nullable',
                'integer',
                $this->tenantExists('categories'),
            ],

            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Auto-referencia directa (A→A): comprobación barata que sí corresponde al
     * borde. El ciclo indirecto (A→B→A) esta en CategoryService (H-17).
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if (! $this->filled('parent_id')) {
                    return;
                }

                if ((int) $this->input('parent_id') === $this->routeId('category')) {
                    $validator->errors()->add(
                        'parent_id',
                        'Una categoría no puede ser su propia categoría padre.'
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
            'name.required'     => 'El nombre de la categoría no puede quedar vacío.',
            'name.max'          => 'El nombre no puede exceder los 120 caracteres.',
            'name.unique'       => 'Ya existe otra categoría activa con este nombre en el negocio.',
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
