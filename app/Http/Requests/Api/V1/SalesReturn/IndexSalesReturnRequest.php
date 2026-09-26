<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\SalesReturn;

use App\Enums\SalesReturnStatus;
use App\Http\Requests\BaseTenantRequest;
use App\Http\Requests\Concerns\HasDateRangeFilter;
use App\Http\Requests\Concerns\HasPaginationRules;
use Illuminate\Validation\Rule;

final class IndexSalesReturnRequest extends BaseTenantRequest
{
    use HasDateRangeFilter;
    use HasPaginationRules;

    public function authorize(): bool
    {
        return true; // SalesReturnPolicy::viewAny.
    }

    protected function prepareForValidation(): void
    {
        $this->stripEmptyFilters();
    }

    /**
     * Allowlist de ordenamiento (H-04). Obligatorio: HasPaginationRules lo declara abstracto.
     *
     * @return array<int, string>
     */
    protected function sortableColumns(): array
    {
        return ['id', 'code', 'returned_at', 'status', 'total_returned', 'created_at'];
    }

    public function rules(): array
    {
        return array_merge(
            [
                // invoices NO es soft-deletable ⇒ excludeTrashed:false (evita 42S22 → 500).
                'invoice_id'  => ['sometimes', 'integer', $this->tenantExists('invoices', 'id', excludeTrashed: false)],
                'customer_id' => ['sometimes', 'integer', $this->tenantExists('customers')], // soft-deletable
                'status'      => ['sometimes', 'string', Rule::in(SalesReturnStatus::values())],
            ],
            $this->dateRangeRules(),
            $this->paginationRules(),
        );
    }

    public function messages(): array
    {
        return array_merge($this->dateRangeMessages(), $this->paginationMessages());
    }

    public function attributes(): array
    {
        return array_merge(
            $this->dateRangeAttributes(),
            $this->paginationAttributes(),
            ['invoice_id' => 'factura', 'customer_id' => 'cliente', 'status' => 'estado'],
        );
    }
}
