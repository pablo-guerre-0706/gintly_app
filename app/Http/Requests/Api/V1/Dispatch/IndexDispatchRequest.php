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

    protected function prepareForValidation(): void
    {
        $this->stripEmptyFilters();
    }

    /**
     * Allowlist de ordenamiento (H-04). Sin él, HasPaginationRules es abstracto y la clase
     * ni siquiera carga; con columnas arbitrarias, 'sort' sería un vector de inyección.
     *
     * @return array<int, string>
     */
    protected function sortableColumns(): array
    {
        return ['id', 'code', 'dispatched_at', 'status', 'created_at'];
    }

    public function rules(): array
    {
        return array_merge(
            [
                // invoices NO es soft-deletable ⇒ excludeTrashed:false (evita 42S22 → 500).
                'invoice_id'   => ['sometimes', 'integer', $this->tenantExists('invoices', 'id', excludeTrashed: false)],
                'warehouse_id' => ['sometimes', 'integer', $this->tenantExists('warehouses')], // soft-deletable
                'status'       => ['sometimes', 'string', Rule::in(DispatchStatus::values())],
            ],
            $this->dateRangeRules(),
            $this->paginationRules(),
        );
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return array_merge($this->dateRangeMessages(), $this->paginationMessages());
    }

    public function attributes(): array
    {
        return array_merge(
            $this->dateRangeAttributes(),
            $this->paginationAttributes(),
            [
                'invoice_id'   => 'factura',
                'warehouse_id' => 'bodega',
                'status'       => 'estado',
            ],
        );
    }
}
