<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Product;

use App\Enums\ProductType;
use App\Http\Requests\BaseTenantRequest;
use App\Http\Requests\Concerns\HasPaginationRules;
use App\Models\Product;
use Illuminate\Validation\Rule;

final class IndexProductRequest extends BaseTenantRequest
{
    use HasPaginationRules;

    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', Product::class) ?? false;
    }

    /**
     * @return array<int, string>
     */
    protected function sortableColumns(): array
    {
        return ['name', 'sku', 'sale_price', 'type', 'is_active', 'created_at'];
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return array_merge($this->paginationRules(), [
            'type'        => ['sometimes', Rule::enum(ProductType::class)],
            'category_id' => ['sometimes', 'integer', $this->tenantExists('categories')],
            'is_active'   => ['sometimes', 'boolean'],
            // 'available' se interpreta contra stock (MOD-03); aquí solo se
            // valida la forma para no romper el binding cuando llegue.
            'available'   => ['sometimes', 'boolean'],
            'search'      => ['sometimes', 'string', 'min:2', 'max:160'],
        ]);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return array_merge($this->paginationMessages(), [
            'type.enum'           => 'El tipo de producto indicado no es válido.',
            'category_id.integer' => 'La categoría indicada en el filtro no es válida.',
            'category_id.exists'  => 'La categoría indicada no existe o no pertenece a su negocio.',
            'is_active.boolean'   => 'El filtro de estado debe ser verdadero o falso.',
            'available.boolean'   => 'El filtro de disponibilidad debe ser verdadero o falso.',
            'search.min'          => 'El término de búsqueda debe tener al menos 2 caracteres.',
            'search.max'          => 'El término de búsqueda no puede exceder los 160 caracteres.',
        ]);
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return array_merge($this->paginationAttributes(), [
            'type'        => 'tipo',
            'category_id' => 'categoría',
            'is_active'   => 'estado',
            'available'   => 'disponibilidad',
            'search'      => 'término de búsqueda',
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
