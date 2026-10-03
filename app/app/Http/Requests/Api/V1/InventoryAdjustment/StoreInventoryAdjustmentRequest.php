<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\InventoryAdjustment;

use App\Enums\InventoryAdjustmentType;
use App\Http\Requests\BaseTenantRequest;
use App\Models\InventoryAdjustment;
use Illuminate\Validation\Rule;


final class StoreInventoryAdjustmentRequest extends BaseTenantRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', InventoryAdjustment::class) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'warehouse_id' => ['required', 'integer', $this->tenantExists('warehouses')->where('is_active', true)],

            'product_id' => ['required', 'integer', $this->tenantExists('products')],

            'type' => ['required', Rule::enum(InventoryAdjustmentType::class)],

            'quantity' => ['required', 'numeric', 'decimal:0,3', 'gt:0'],

            'reason' => ['required', 'string', 'max:255'],

            'physical_count_id' => [
                'nullable',
                'integer',
                $this->tenantExists('physical_counts', 'id', excludeTrashed: false),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'warehouse_id.required'    => 'La bodega es obligatoria.',
            'warehouse_id.exists'      => 'La bodega no existe, está inactiva o no pertenece a su negocio.',
            'product_id.required'      => 'El producto es obligatorio.',
            'product_id.exists'        => 'El producto no existe o no pertenece a su negocio.',
            'type.required'            => 'El tipo de ajuste es obligatorio.',
            'type.enum'                => 'El tipo de ajuste debe ser merma, sobrante o corrección.',
            'quantity.required'        => 'La cantidad del ajuste es obligatoria.',
            'quantity.decimal'         => 'La cantidad admite un máximo de tres decimales.',
            'quantity.gt'              => 'La cantidad debe ser mayor que cero.',
            'reason.required'          => 'El motivo del ajuste es obligatorio.',
            'reason.max'               => 'El motivo no puede exceder los 255 caracteres.',
            'physical_count_id.exists' => 'El conteo indicado no existe o no pertenece a su negocio.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'warehouse_id'      => 'bodega',
            'product_id'        => 'producto',
            'type'              => 'tipo',
            'quantity'          => 'cantidad',
            'reason'            => 'motivo',
            'physical_count_id' => 'conteo físico',
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->reason)) {
            $this->merge(['reason' => trim($this->reason)]);
        }
    }
}
