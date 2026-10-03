<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\CashSession;

use App\Enums\CashSessionStatus;
use App\Http\Requests\BaseTenantRequest;
use App\Http\Requests\Concerns\HasDateRangeFilter;
use App\Http\Requests\Concerns\HasPaginationRules;
use App\Models\CashSession;
use Illuminate\Validation\Rule;

final class IndexCashSessionRequest extends BaseTenantRequest
{
    use HasDateRangeFilter;
    use HasPaginationRules;

    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', CashSession::class) ?? false;
    }

    /**
     * @return array<int, string>
     */
    protected function sortableColumns(): array
    {
        return ['opened_at', 'closed_at', 'status', 'created_at'];
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return array_merge($this->paginationRules(), $this->dateRangeRules(), [
            'cash_register_id' => ['sometimes', 'integer', $this->tenantExists('cash_registers')],
            'opened_by'        => ['sometimes', 'integer', $this->tenantExists('users', 'id', excludeTrashed: true)],
            'status'           => ['sometimes', Rule::enum(CashSessionStatus::class)],
        ]);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return array_merge($this->paginationMessages(), $this->dateRangeMessages(), [
            'cash_register_id.exists' => 'La caja indicada no existe o no pertenece a su negocio.',
            'opened_by.exists'        => 'El cajero indicado no existe o no pertenece a su negocio.',
            'status.enum'             => 'El estado indicado no es válido (abierta, cerrada o descuadrada).',
        ]);
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return array_merge($this->paginationAttributes(), $this->dateRangeAttributes(), [
            'cash_register_id' => 'caja',
            'opened_by'        => 'cajero',
            'status'           => 'estado',
        ]);
    }

    protected function prepareForValidation(): void
    {
        $this->stripEmptyFilters();
    }
}
