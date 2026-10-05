<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\CashRegisterAssignment;

use App\Http\Requests\BaseTenantRequest;
use App\Http\Requests\Concerns\HasPaginationRules;
use App\Models\CashRegisterAssignment;

/**
 * MOD-06 · Listado de asignaciones Caja–Cajero. ROL-01/ROL-02 ven todas las del negocio con filtros;
 * ROL-03 queda acotado a las suyas en el controlador (el filtro user_id no amplía su alcance).
 */
final class IndexCashRegisterAssignmentRequest extends BaseTenantRequest
{
    use HasPaginationRules;

    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', CashRegisterAssignment::class) ?? false;
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
            'cash_register_id' => ['sometimes', 'integer', $this->tenantExists('cash_registers')],
            'user_id'          => ['sometimes', 'integer', $this->tenantExists('users', 'id', excludeTrashed: true)],
            'active'           => ['sometimes', 'boolean'],
        ]);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return array_merge($this->paginationMessages(), [
            'cash_register_id.exists' => 'La caja indicada no existe o no pertenece a su negocio.',
            'user_id.exists'          => 'El cajero indicado no existe o no pertenece a su negocio.',
            'active.boolean'          => 'El filtro de vigencia debe ser verdadero o falso.',
        ]);
    }

    protected function prepareForValidation(): void
    {
        $this->stripEmptyFilters();
    }
}
