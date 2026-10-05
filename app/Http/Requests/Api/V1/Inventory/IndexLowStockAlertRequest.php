<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Inventory;

use App\Http\Requests\BaseTenantRequest;
use App\Http\Requests\Concerns\HasPaginationRules;
use App\Models\StockLevel;

/**
 * MOD-03 (microcierre) · Filtros del aviso de mínimo. Autorización: la misma que la consulta de inventario
 * (StockLevelPolicy::viewAny → ROL-01/02 y bodeguero con inventario.ver). El aislamiento por bodega asignada
 * para ROL-03 lo aplica el scope forOperatorWarehouses en el controlador.
 */
final class IndexLowStockAlertRequest extends BaseTenantRequest
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
        return ['quantity', 'reserved_quantity', 'min_stock', 'updated_at'];
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return array_merge($this->paginationRules(), [
            'branch_id'    => ['sometimes', 'integer', $this->tenantExists('branches')],
            'warehouse_id' => ['sometimes', 'integer', $this->tenantExists('warehouses')],
            'product_id'   => ['sometimes', 'integer', $this->tenantExists('products')],
        ]);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return array_merge($this->paginationMessages(), [
            'branch_id.exists'    => 'La sucursal indicada no existe o no pertenece a su negocio.',
            'warehouse_id.exists' => 'La bodega indicada no existe o no pertenece a su negocio.',
            'product_id.exists'   => 'El producto indicado no existe o no pertenece a su negocio.',
        ]);
    }

    protected function prepareForValidation(): void
    {
        $this->stripEmptyFilters();
    }
}
