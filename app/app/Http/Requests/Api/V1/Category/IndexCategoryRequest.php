<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Category;

use App\Http\Requests\BaseTenantRequest;
use App\Http\Requests\Concerns\HasPaginationRules;
use App\Models\Category;

final class IndexCategoryRequest extends BaseTenantRequest
{
    use HasPaginationRules;

    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', Category::class) ?? false;
    }

    /**
     * @return array<int, string>
     */
    protected function sortableColumns(): array
    {
        return ['name', 'is_active', 'created_at'];
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return array_merge($this->paginationRules(), [
            'parent_id' => ['sometimes', 'integer', $this->tenantExists('categories')],
            'is_active' => ['sometimes', 'boolean'],
            // tree=true devuelve el árbol jerárquico completo (raíces con hijos
            // anidados) en lugar de la lista paginada. Lo interpreta el controlador.
            'tree'      => ['sometimes', 'boolean'],
            'search'    => ['sometimes', 'string', 'min:2', 'max:120'],
        ]);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return array_merge($this->paginationMessages(), [
            'parent_id.integer' => 'La categoría padre indicada no es válida.',
            'parent_id.exists'  => 'La categoría padre indicada no existe o no pertenece a su negocio.',
            'is_active.boolean' => 'El filtro de estado debe ser verdadero o falso.',
            'tree.boolean'      => 'El indicador de árbol debe ser verdadero o falso.',
            'search.min'        => 'El término de búsqueda debe tener al menos 2 caracteres.',
            'search.max'        => 'El término de búsqueda no puede exceder los 120 caracteres.',
        ]);
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return array_merge($this->paginationAttributes(), [
            'parent_id' => 'categoría padre',
            'is_active' => 'estado',
            'tree'      => 'árbol',
            'search'    => 'término de búsqueda',
        ]);
    }

    protected function prepareForValidation(): void
    {
        $this->stripEmptyFilters();

        if (is_string($this->search)) {
            $this->merge(['search' => trim($this->search)]);
        }
    }
}
