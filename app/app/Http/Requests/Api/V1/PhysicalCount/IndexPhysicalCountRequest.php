<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\PhysicalCount;

use App\Enums\PhysicalCountStatus;
use App\Http\Requests\BaseTenantRequest;
use App\Http\Requests\Concerns\HasPaginationRules;
use App\Models\PhysicalCount;
use Illuminate\Validation\Rule;

final class IndexPhysicalCountRequest extends BaseTenantRequest
{
    use HasPaginationRules;

    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', PhysicalCount::class) ?? false;
    }

    /**
     * @return array<int, string>
     */
    protected function sortableColumns(): array
    {
        return ['counted_at', 'difference', 'status', 'created_at'];
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return array_merge($this->paginationRules(), [
            'warehouse_id' => ['sometimes', 'integer', $this->tenantExists('warehouses')],
            'product_id'   => ['sometimes', 'integer', $this->tenantExists('products')],
            'status'       => ['sometimes', Rule::enum(PhysicalCountStatus::class)],
        ]);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return array_merge($this->paginationMessages(), [
            'warehouse_id.exists' => 'La bodega indicada no existe o no pertenece a su negocio.',
            'product_id.exists'   => 'El producto indicado no existe o no pertenece a su negocio.',
            'status.enum'         => 'El estado indicado no es válido (abierto, justificado o ajustado).',
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
            'status'       => 'estado',
        ]);
    }

    protected function prepareForValidation(): void
    {
        $this->stripEmptyFilters();
    }
}
