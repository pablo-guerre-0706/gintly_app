<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\StockTransfer;

use App\Enums\StockTransferStatus;
use App\Http\Requests\BaseTenantRequest;
use App\Http\Requests\Concerns\HasPaginationRules;
use App\Models\StockTransfer;
use Illuminate\Validation\Rule;


final class IndexStockTransferRequest extends BaseTenantRequest
{
    use HasPaginationRules;

    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', StockTransfer::class) ?? false;
    }

    /**
     * @return array<int, string>
     */
    protected function sortableColumns(): array
    {
        return ['code', 'status', 'transferred_at', 'created_at'];
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return array_merge($this->paginationRules(), [
            'from_warehouse_id' => ['sometimes', 'integer', $this->tenantExists('warehouses')],
            'to_warehouse_id'   => ['sometimes', 'integer', $this->tenantExists('warehouses')],
            'status'            => ['sometimes', Rule::enum(StockTransferStatus::class)],
        ]);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return array_merge($this->paginationMessages(), [
            'from_warehouse_id.exists' => 'La bodega origen indicada no existe o no pertenece a su negocio.',
            'to_warehouse_id.exists'   => 'La bodega destino indicada no existe o no pertenece a su negocio.',
            'status.enum'              => 'El estado indicado no es válido (pendiente, completado o cancelado).',
        ]);
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return array_merge($this->paginationAttributes(), [
            'from_warehouse_id' => 'bodega origen',
            'to_warehouse_id'   => 'bodega destino',
            'status'            => 'estado',
        ]);
    }

    protected function prepareForValidation(): void
    {
        $this->stripEmptyFilters();
    }
}
