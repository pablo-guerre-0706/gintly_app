<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\CashRegisterAssignment;

use App\Http\Requests\BaseTenantRequest;
use App\Models\CashRegisterAssignment;

/**
 * MOD-06 · Alta de asignación Caja–Cajero (ROL-01/ROL-02). Existencia + pertenencia al tenant aquí; las
 * invariantes de dominio (misma sucursal, perfil cajero, conflictos, sesión abierta) viven en el Service.
 */
final class StoreCashRegisterAssignmentRequest extends BaseTenantRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', CashRegisterAssignment::class) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'cash_register_id' => ['required', 'integer', $this->tenantExists('cash_registers')],
            'user_id'          => ['required', 'integer', $this->tenantExists('users', 'id', excludeTrashed: true)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'cash_register_id.required' => 'La caja es obligatoria.',
            'cash_register_id.exists'   => 'La caja no existe o no pertenece a su negocio.',
            'user_id.required'          => 'El cajero es obligatorio.',
            'user_id.exists'            => 'El usuario no existe o no pertenece a su negocio.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'cash_register_id' => 'caja',
            'user_id'          => 'cajero',
        ];
    }
}
