<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\PurchaseOrder;

use App\Http\Requests\BaseTenantRequest;
use App\Models\PurchaseOrder;


// Editable SOLO en 'borrador'
final class UpdatePurchaseOrderRequest extends BaseTenantRequest
{
    public function authorize(): bool
    {
        $target = $this->route('purchase_order');

        return $this->user()?->can('update', $target instanceof PurchaseOrder ? $target : PurchaseOrder::class) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'ordered_at' => ['sometimes', 'required', 'date_format:Y-m-d'],
            'notes'      => ['sometimes', 'nullable', 'string', 'max:500'],

            'items'   => ['sometimes', 'required', 'array', 'min:1'],
            'items.*.product_id' => ['required_with:items', 'integer', 'distinct', $this->tenantExists('products')],
            'items.*.ordered_quantity' => ['required_with:items', 'numeric', 'decimal:0,3', 'gt:0'],
            'items.*.agreed_unit_cost' => ['required_with:items', 'numeric', 'decimal:0,4', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'ordered_at.date_format' => 'La fecha de la orden debe expresarse en formato AAAA-MM-DD.',
            'notes.max'              => 'Las observaciones no pueden exceder los 500 caracteres.',
            'items.min'              => 'La orden debe conservar al menos un producto.',
            'items.*.product_id.distinct' => 'No repita el mismo producto en varias líneas.',
            'items.*.product_id.exists'   => 'Un producto indicado no existe o no pertenece a su negocio.',
            'items.*.ordered_quantity.gt' => 'La cantidad ordenada debe ser mayor que cero.',
            'items.*.agreed_unit_cost.min' => 'El costo pactado no puede ser negativo.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'ordered_at' => 'fecha de la orden',
            'notes'      => 'observaciones',
            'items.*.product_id'       => 'producto',
            'items.*.ordered_quantity' => 'cantidad ordenada',
            'items.*.agreed_unit_cost' => 'costo pactado',
        ];
    }
}
