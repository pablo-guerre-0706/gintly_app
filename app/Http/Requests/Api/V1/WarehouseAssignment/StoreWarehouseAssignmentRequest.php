<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\WarehouseAssignment;

use App\Http\Requests\BaseTenantRequest;
use App\Models\WarehouseAssignment;

/**
 * MOD-03 · Alta de asignación Bodega–Bodeguero (ROL-01/ROL-02). Existencia + tenant aquí; las invariantes
 * (misma sucursal, perfil bodeguero, par activo único) viven en WarehouseAssignmentService.
 */
final class StoreWarehouseAssignmentRequest extends BaseTenantRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', WarehouseAssignment::class) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'warehouse_id' => ['required', 'integer', $this->tenantExists('warehouses')],
            'user_id'      => ['required', 'integer', $this->tenantExists('users', 'id', excludeTrashed: true)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'warehouse_id.required' => 'La bodega es obligatoria.',
            'warehouse_id.exists'   => 'La bodega no existe o no pertenece a su negocio.',
            'user_id.required'      => 'El bodeguero es obligatorio.',
            'user_id.exists'        => 'El usuario no existe o no pertenece a su negocio.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'warehouse_id' => 'bodega',
            'user_id'      => 'bodeguero',
        ];
    }
}
