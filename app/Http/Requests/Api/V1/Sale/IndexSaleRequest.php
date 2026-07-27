<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Sale;

use App\Enums\SaleStatus;
use App\Http\Requests\BaseTenantRequest;
use App\Http\Requests\Concerns\HasDateRangeFilter;
use App\Http\Requests\Concerns\HasPaginationRules;
use App\Models\Sale;
use Illuminate\Validation\Rule;

final class IndexSaleRequest extends BaseTenantRequest
{
    use HasDateRangeFilter;
    use HasPaginationRules;

    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', Sale::class) ?? false;
    }

    /**
     * @return array<int, string>
     */
    protected function sortableColumns(): array
    {
        return ['code', 'status', 'subtotal', 'opened_at', 'created_at'];
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return array_merge($this->paginationRules(), $this->dateRangeRules(), [
            'status'      => ['sometimes', Rule::enum(SaleStatus::class)],
            'customer_id' => ['sometimes', 'integer', $this->tenantExists('customers')],
            'branch_id'   => ['sometimes', 'integer', $this->tenantExists('branches')],
        ]);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return array_merge($this->paginationMessages(), $this->dateRangeMessages(), [
            'status.enum'         => 'El estado indicado no es válido.',
            'customer_id.exists'  => 'El cliente indicado no existe o no pertenece a su negocio.',
            'branch_id.exists'    => 'La sucursal indicada no existe o no pertenece a su negocio.',
        ]);
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return array_merge($this->paginationAttributes(), $this->dateRangeAttributes(), [
            'status'      => 'estado',
            'customer_id' => 'cliente',
            'branch_id'   => 'sucursal',
        ]);
    }

    protected function prepareForValidation(): void
    {
        $this->stripEmptyFilters();
    }
}
