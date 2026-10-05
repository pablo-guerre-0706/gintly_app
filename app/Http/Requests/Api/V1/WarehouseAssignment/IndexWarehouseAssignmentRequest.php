<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\WarehouseAssignment;

use App\Http\Requests\BaseTenantRequest;
use App\Http\Requests\Concerns\HasPaginationRules;
use App\Models\WarehouseAssignment;

/**
 * MOD-03 · Listado de asignaciones Bodega–Bodeguero. ROL-01/ROL-02 todas (con filtros); ROL-03 acotado a
 * las suyas en el controlador (el filtro user_id no amplía su alcance).
 */
final class IndexWarehouseAssignmentRequest extends BaseTenantRequest
{
    use HasPaginationRules;

    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', WarehouseAssignment::class) ?? false;
    }

    /**
     * @return array<int, string>
     */
    protected function sortableColumns(): array
    {
        return ['assigned_at', 'ended_at', 'created_at'];
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return array_merge($this->paginationRules(), [
            'warehouse_id' => ['sometimes', 'integer', $this->tenantExists('warehouses')],
            'user_id'      => ['sometimes', 'integer', $this->tenantExists('users', 'id', excludeTrashed: true)],
            'active'       => ['sometimes', 'boolean'],
        ]);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return array_merge($this->paginationMessages(), [
            'warehouse_id.exists' => 'La bodega indicada no existe o no pertenece a su negocio.',
            'user_id.exists'      => 'El bodeguero indicado no existe o no pertenece a su negocio.',
            'active.boolean'      => 'El filtro de vigencia debe ser verdadero o falso.',
        ]);
    }

    protected function prepareForValidation(): void
    {
        $this->stripEmptyFilters();
    }
}
