<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\InventoryAdjustment;

use App\Enums\InventoryAdjustmentType;
use App\Http\Requests\BaseTenantRequest;
use App\Http\Requests\Concerns\HasPaginationRules;
use App\Models\InventoryAdjustment;
use Illuminate\Validation\Rule;

final class IndexInventoryAdjustmentRequest extends BaseTenantRequest
{
    use HasPaginationRules;

    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', InventoryAdjustment::class) ?? false;
    }

    /**
     * @return array<int, string>
     */
    protected function sortableColumns(): array
    {
        return ['type', 'adjusted_at', 'created_at'];
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return array_merge($this->paginationRules(), [
            'warehouse_id'      => ['sometimes', 'integer', $this->tenantExists('warehouses')],
            'type'              => ['sometimes', Rule::enum(InventoryAdjustmentType::class)],
            'physical_count_id' => ['sometimes', 'integer', $this->tenantExists('physical_counts', 'id', excludeTrashed: false)],
        ]);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return array_merge($this->paginationMessages(), [
            'warehouse_id.exists'      => 'La bodega indicada no existe o no pertenece a su negocio.',
            'type.enum'                => 'El tipo de ajuste indicado no es válido (merma, sobrante o corrección).',
            'physical_count_id.exists' => 'El conteo indicado no existe o no pertenece a su negocio.',
        ]);
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return array_merge($this->paginationAttributes(), [
            'warehouse_id'      => 'bodega',
            'type'              => 'tipo',
            'physical_count_id' => 'conteo físico',
        ]);
    }

    protected function prepareForValidation(): void
    {
        $this->stripEmptyFilters();
    }
}
