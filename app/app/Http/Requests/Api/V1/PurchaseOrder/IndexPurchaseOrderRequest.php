<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\PurchaseOrder;

use App\Enums\PurchaseOrderStatus;
use App\Http\Requests\BaseTenantRequest;
use App\Http\Requests\Concerns\HasPaginationRules;
use App\Models\PurchaseOrder;
use Illuminate\Validation\Rule;

final class IndexPurchaseOrderRequest extends BaseTenantRequest
{
    use HasPaginationRules;

    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', PurchaseOrder::class) ?? false;
    }

    /**
     * @return array<int, string>
     */
    protected function sortableColumns(): array
    {
        return ['code', 'status', 'expected_total', 'ordered_at', 'created_at'];
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return array_merge($this->paginationRules(), [
            'supplier_id' => ['sometimes', 'integer', $this->tenantExists('suppliers')],
            'status'      => ['sometimes', Rule::enum(PurchaseOrderStatus::class)],
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
        ]);
    }

    protected function prepareForValidation(): void
    {
        $this->stripEmptyFilters();
    }
}
