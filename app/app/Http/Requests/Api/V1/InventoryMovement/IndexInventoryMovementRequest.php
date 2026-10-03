<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\InventoryMovement;

use App\Enums\InventoryMovementType;
use App\Http\Requests\BaseTenantRequest;
use App\Http\Requests\Concerns\HasDateRangeFilter;
use App\Http\Requests\Concerns\HasPaginationRules;
use App\Models\InventoryMovement;
use Illuminate\Validation\Rule;


// Kardex solo-lectura: solo hay Index.
final class IndexInventoryMovementRequest extends BaseTenantRequest
{
    use HasDateRangeFilter;
    use HasPaginationRules;

    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', InventoryMovement::class) ?? false;
    }

    /**
     * @return array<int, string>
     */
    protected function sortableColumns(): array
    {
        return ['created_at'];
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return array_merge($this->paginationRules(), $this->dateRangeRules(), [
            'product_id'   => ['sometimes', 'integer', $this->tenantExists('products')],
            'warehouse_id' => ['sometimes', 'integer', $this->tenantExists('warehouses')],
            'type'         => ['sometimes', Rule::enum(InventoryMovementType::class)],
        ]);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return array_merge($this->paginationMessages(), $this->dateRangeMessages(), [
            'product_id.exists'   => 'El producto indicado no existe o no pertenece a su negocio.',
            'warehouse_id.exists' => 'La bodega indicada no existe o no pertenece a su negocio.',
            'type.enum'           => 'El tipo de movimiento indicado no es válido.',
        ]);
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return array_merge($this->paginationAttributes(), $this->dateRangeAttributes(), [
            'product_id'   => 'producto',
            'warehouse_id' => 'bodega',
            'type'         => 'tipo',
        ]);
    }

    protected function prepareForValidation(): void
    {
        $this->stripEmptyFilters();
    }
}
