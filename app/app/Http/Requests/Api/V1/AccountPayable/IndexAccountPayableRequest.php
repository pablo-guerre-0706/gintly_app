<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\AccountPayable;

use App\Enums\AccountPayableStatus;
use App\Http\Requests\BaseTenantRequest;
use App\Http\Requests\Concerns\HasPaginationRules;
use App\Models\AccountPayable;
use Illuminate\Validation\Rule;

final class IndexAccountPayableRequest extends BaseTenantRequest
{
    use HasPaginationRules;

    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', AccountPayable::class) ?? false;
    }

    /**
     * @return array<int, string>
     */
    protected function sortableColumns(): array
    {
        return ['total_amount', 'paid_amount', 'status', 'due_date', 'created_at'];
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return array_merge($this->paginationRules(), [
            'supplier_id' => ['sometimes', 'integer', $this->tenantExists('suppliers')],
            'status'      => ['sometimes', Rule::enum(AccountPayableStatus::class)],
            'overdue'     => ['sometimes', 'boolean'],
        ]);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return array_merge($this->paginationMessages(), [
            'supplier_id.exists' => 'El proveedor indicado no existe o no pertenece a su negocio.',
            'status.enum'        => 'El estado indicado no es válido.',
            'overdue.boolean'    => 'El filtro de vencidas debe ser verdadero o falso.',
        ]);
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return array_merge($this->paginationAttributes(), [
            'supplier_id' => 'proveedor',
            'status'      => 'estado',
            'overdue'     => 'vencidas',
        ]);
    }

    protected function prepareForValidation(): void
    {
        $this->stripEmptyFilters();
    }
}
