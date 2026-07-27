<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\GoodsReceipt;

use App\Enums\PurchaseOrderStatus;
use App\Http\Requests\BaseTenantRequest;
use App\Models\GoodsReceipt;
use Illuminate\Validation\Rule;


final class StoreGoodsReceiptRequest extends BaseTenantRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', GoodsReceipt::class) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            // receivable: orden en estado emitida o parcial.
            'purchase_order_id' => [
                'required',
                'integer',
                Rule::exists('purchase_orders', 'id')
                    ->where('business_id', $this->businessId())
                    ->whereIn('status', [
                        PurchaseOrderStatus::Emitida->value,
                        PurchaseOrderStatus::Parcial->value,
                    ])
                    ->whereNull('deleted_at'),
            ],

            'warehouse_id' => ['required', 'integer', $this->tenantExists('warehouses')->where('is_active', true)],

            'supplier_invoice_number' => ['nullable', 'string', 'max:60'],
            'supplier_invoice_total'  => ['nullable', 'numeric', 'decimal:0,2', 'min:0'],

            // Tolerancia parametrizable, default 0 (RF-04-03). Escala 4 para
            // comparaciones de costo unitario.
            'tolerance' => ['sometimes', 'numeric', 'decimal:0,4', 'min:0'],

            'lines'   => ['required', 'array', 'min:1'],
            'lines.*.purchase_order_item_id' => ['required', 'integer', 'distinct', $this->tenantExists('purchase_order_items', 'id', excludeTrashed: false)],
            'lines.*.received_quantity'      => ['required', 'numeric', 'decimal:0,3', 'gt:0'],
            'lines.*.invoiced_unit_cost'     => ['required', 'numeric', 'decimal:0,4', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'purchase_order_id.required' => 'La orden de compra es obligatoria.',
            'purchase_order_id.exists'   => 'La orden no existe, no está en estado receptible o no pertenece a su negocio.',
            'warehouse_id.required'      => 'La bodega receptora es obligatoria.',
            'warehouse_id.exists'        => 'La bodega no existe, está inactiva o no pertenece a su negocio.',
            'supplier_invoice_number.max' => 'El número de factura no puede exceder los 60 caracteres.',
            'supplier_invoice_total.decimal' => 'El total de la factura admite un máximo de dos decimales.',
            'supplier_invoice_total.min'  => 'El total de la factura no puede ser negativo.',
            'tolerance.decimal'          => 'La tolerancia admite un máximo de cuatro decimales.',
            'tolerance.min'              => 'La tolerancia no puede ser negativa.',
            'lines.required'             => 'Debe incluir al menos una línea de recepción.',
            'lines.min'                  => 'Debe incluir al menos una línea de recepción.',
            'lines.*.purchase_order_item_id.required' => 'Cada línea debe referenciar una línea de la orden.',
            'lines.*.purchase_order_item_id.distinct' => 'No repita la misma línea de orden en la recepción.',
            'lines.*.purchase_order_item_id.exists'   => 'Una línea de orden indicada no existe o no pertenece a su negocio.',
            'lines.*.received_quantity.required' => 'Cada línea debe indicar la cantidad recibida.',
            'lines.*.received_quantity.gt'       => 'La cantidad recibida debe ser mayor que cero.',
            'lines.*.invoiced_unit_cost.required' => 'Cada línea debe indicar el costo facturado.',
            'lines.*.invoiced_unit_cost.min'      => 'El costo facturado no puede ser negativo.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'purchase_order_id'       => 'orden de compra',
            'warehouse_id'            => 'bodega receptora',
            'supplier_invoice_number' => 'número de factura',
            'supplier_invoice_total'  => 'total de la factura',
            'tolerance'               => 'tolerancia',
            'lines'                   => 'líneas de recepción',
            'lines.*.purchase_order_item_id' => 'línea de orden',
            'lines.*.received_quantity'      => 'cantidad recibida',
            'lines.*.invoiced_unit_cost'     => 'costo facturado',
        ];
    }
}
