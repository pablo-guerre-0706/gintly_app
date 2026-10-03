<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Warehouse;

use App\Http\Requests\BaseTenantRequest;
use App\Http\Requests\Concerns\HasPaginationRules;
use App\Models\Warehouse;

final class IndexWarehouseRequest extends BaseTenantRequest
{
    use HasPaginationRules;

    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', Warehouse::class) ?? false;
    }

    /**
     * @return array<int, string>
     */
    protected function sortableColumns(): array
    {
        return ['name', 'is_default', 'is_active', 'created_at'];
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return array_merge($this->paginationRules(), [
            'branch_id'  => ['sometimes', 'integer', $this->tenantExists('branches')],
            'is_active'  => ['sometimes', 'boolean'],
            'is_default' => ['sometimes', 'boolean'],
        ]);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return array_merge($this->paginationMessages(), [
            'branch_id.integer'  => 'La sucursal indicada en el filtro no es válida.',
            'branch_id.exists'   => 'La sucursal indicada no existe o no pertenece a su negocio.',
            'is_active.boolean'  => 'El filtro de estado debe ser verdadero o falso.',
            'is_default.boolean' => 'El filtro de bodega predeterminada debe ser verdadero o falso.',
        ]);
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return array_merge($this->paginationAttributes(), [
            'branch_id'  => 'sucursal',
            'is_active'  => 'estado',
            'is_default' => 'bodega predeterminada',
        ]);
    }

    protected function prepareForValidation(): void
    {
        $this->stripEmptyFilters();
    }
}
