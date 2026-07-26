<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\StockLevel;

use App\Http\Requests\BaseTenantRequest;
use App\Http\Requests\Concerns\HasPaginationRules;
use App\Models\StockLevel;

final class IndexStockLevelRequest extends BaseTenantRequest
{
    use HasPaginationRules;

    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', StockLevel::class) ?? false;
    }

    /**
     * @return array<int, string>
     */
    protected function sortableColumns(): array
    {
        return ['quantity', 'reserved_quantity', 'average_cost', 'updated_at'];
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return array_merge($this->paginationRules(), [
            'warehouse_id' => ['sometimes', 'integer', $this->tenantExists('warehouses')],
            'product_id'   => ['sometimes', 'integer', $this->tenantExists('products')],
            'below_min'    => ['sometimes', 'boolean'],
            'search'       => ['sometimes', 'string', 'min:2', 'max:160'],
        ]);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return array_merge($this->paginationMessages(), [
            'warehouse_id.integer' => 'La bodega indicada en el filtro no es válida.',
            'warehouse_id.exists'  => 'La bodega indicada no existe o no pertenece a su negocio.',
            'product_id.integer'   => 'El producto indicado en el filtro no es válido.',
            'product_id.exists'    => 'El producto indicado no existe o no pertenece a su negocio.',
            'below_min.boolean'    => 'El filtro de existencias bajo mínimo debe ser verdadero o falso.',
            'search.min'           => 'El término de búsqueda debe tener al menos 2 caracteres.',
            'search.max'           => 'El término de búsqueda no puede exceder los 160 caracteres.',
        ]);
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return array_merge($this->paginationAttributes(), [
            'warehouse_id' => 'bodega',
            'product_id'   => 'producto',
            'below_min'    => 'existencias bajo mínimo',
            'search'       => 'término de búsqueda',
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
