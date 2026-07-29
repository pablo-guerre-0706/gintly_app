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

    public function rules(): array
    {
        return array_merge(
            [
                'invoice_id'  => ['sometimes', 'integer', $this->tenantExists('invoices')],
                'customer_id' => ['sometimes', 'integer', $this->tenantExists('customers')],
                'status'      => ['sometimes', 'string', Rule::in(SalesReturnStatus::values())],
            ],
            $this->dateRangeRules(),
            $this->paginationRules(),
        );
    }

    public function attributes(): array
    {
        return ['invoice_id' => 'factura', 'customer_id' => 'cliente', 'status' => 'estado'];
    }
}
