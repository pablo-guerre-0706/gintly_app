<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Dispatch;

use App\Enums\DispatchStatus;
use App\Http\Requests\Concerns\HasDateRangeFilter;
use App\Http\Requests\Concerns\HasPaginationRules;
use App\Http\Requests\BaseTenantRequest;
use Illuminate\Validation\Rule;

final class IndexDispatchRequest extends BaseTenantRequest
{
    use HasDateRangeFilter;
    use HasPaginationRules;

    public function authorize(): bool
    {
        return true; // DispatchPolicy::viewAny en el controlador.
    }

    public function rules(): array
    {
        return array_merge(
            [
                'invoice_id'   => ['sometimes', 'integer', $this->tenantExists('invoices')],
                'warehouse_id' => ['sometimes', 'integer', $this->tenantExists('warehouses')],
                'status'       => ['sometimes', 'string', Rule::in(DispatchStatus::values())],
            ],
            $this->dateRangeRules(),
            $this->paginationRules(),
        );
    }

    public function attributes(): array
    {
        return [
            'invoice_id'   => 'factura',
            'warehouse_id' => 'bodega',
            'status'       => 'estado',
        ];
    }
}
