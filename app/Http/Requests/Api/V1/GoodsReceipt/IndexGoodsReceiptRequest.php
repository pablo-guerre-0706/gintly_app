<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\GoodsReceipt;

use App\Enums\GoodsReceiptMatchStatus;
use App\Http\Requests\BaseTenantRequest;
use App\Http\Requests\Concerns\HasPaginationRules;
use App\Models\GoodsReceipt;
use Illuminate\Validation\Rule;


final class IndexGoodsReceiptRequest extends BaseTenantRequest
{
    use HasPaginationRules;

    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', GoodsReceipt::class) ?? false;
    }

    /**
     * @return array<int, string>
     */
    protected function sortableColumns(): array
    {
        return ['received_at', 'match_status', 'created_at'];
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return array_merge($this->paginationRules(), [
            'purchase_order_id' => ['sometimes', 'integer', $this->tenantExists('purchase_orders')],
            'match_status'      => ['sometimes', Rule::enum(GoodsReceiptMatchStatus::class)],
        ]);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return array_merge($this->paginationMessages(), [
            'purchase_order_id.exists' => 'La orden indicada no existe o no pertenece a su negocio.',
            'match_status.enum'        => 'El estado de conciliación indicado no es válido.',
        ]);
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return array_merge($this->paginationAttributes(), [
            'purchase_order_id' => 'orden de compra',
            'match_status'      => 'estado de conciliación',
        ]);
    }

    protected function prepareForValidation(): void
    {
        $this->stripEmptyFilters();
    }
}
