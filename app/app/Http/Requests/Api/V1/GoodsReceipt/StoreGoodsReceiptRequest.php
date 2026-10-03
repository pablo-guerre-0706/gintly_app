<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\GoodsReceipt;

use App\Enums\PurchaseOrderStatus;
use App\Enums\RoleName;
use App\Http\Requests\BaseTenantRequest;
use App\Models\GoodsReceipt;
use App\Models\PurchaseOrder;
use App\Models\Warehouse;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

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
            'supplier_invoice_total' => ['nullable', 'numeric', 'decimal:0,2', 'min:0'],

            // Tolerancia parametrizable, default 0 (RF-04-03). Escala 4 para
            // comparaciones de costo unitario.
            'tolerance' => ['sometimes', 'numeric', 'decimal:0,4', 'min:0'],

            'lines' => ['required', 'array', 'min:1'],
            'lines.*.purchase_order_item_id' => ['required', 'integer', 'distinct', $this->tenantExists('purchase_order_items', 'id', excludeTrashed: false)],
            'lines.*.received_quantity' => ['required', 'numeric', 'decimal:0,3', 'gt:0'],
            'lines.*.invoiced_unit_cost' => ['required', 'numeric', 'decimal:0,4', 'min:0'],
        ];
    }

    /**
     * @return array<int, callable> Microcierre: ROL-03 (bodeguero) solo recibe cuando la ORDEN y la
     *                              BODEGA son de SU sucursal (purchase_order.branch_id === warehouse.branch_id === user.branch_id).
     *                              El branch_id del cliente nunca es autoridad; se deriva de la orden/bodega del tenant. Sin sucursal
     *                              asignada falla cerrado. ROL-01/ROL-02 no se acotan. BusinessScope ya aísla el negocio (value()
     *                              devuelve null para recursos ajenos → no revela su existencia; el `exists` de rules() emite el 422).
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $user = $this->user();
            if ($user === null || $user->getRoleNames()->first() !== RoleName::Operator->value) {
                return;
            }

            $userBranch = (int) $user->branch_id; // null → 0: una sucursal real nunca es 0, así falla cerrado.

            $warehouseBranch = Warehouse::query()->whereKey($this->input('warehouse_id'))->value('branch_id');
            if ($warehouseBranch !== null && (int) $warehouseBranch !== $userBranch) {
                $validator->errors()->add('warehouse_id', 'Solo puede recibir en una bodega de su sucursal.');
            }

            $orderBranch = PurchaseOrder::query()->whereKey($this->input('purchase_order_id'))->value('branch_id');
            if ($orderBranch !== null && (int) $orderBranch !== $userBranch) {
                $validator->errors()->add('purchase_order_id', 'Solo puede recibir órdenes de compra de su sucursal.');
            }
        }];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'purchase_order_id.required' => 'La orden de compra es obligatoria.',
            'purchase_order_id.exists' => 'La orden no existe, no está en estado receptible o no pertenece a su negocio.',
            'warehouse_id.required' => 'La bodega receptora es obligatoria.',
            'warehouse_id.exists' => 'La bodega no existe, está inactiva o no pertenece a su negocio.',
            'supplier_invoice_number.max' => 'El número de factura no puede exceder los 60 caracteres.',
            'supplier_invoice_total.decimal' => 'El total de la factura admite un máximo de dos decimales.',
            'supplier_invoice_total.min' => 'El total de la factura no puede ser negativo.',
            'tolerance.decimal' => 'La tolerancia admite un máximo de cuatro decimales.',
            'tolerance.min' => 'La tolerancia no puede ser negativa.',
            'lines.required' => 'Debe incluir al menos una línea de recepción.',
            'lines.min' => 'Debe incluir al menos una línea de recepción.',
            'lines.*.purchase_order_item_id.required' => 'Cada línea debe referenciar una línea de la orden.',
            'lines.*.purchase_order_item_id.distinct' => 'No repita la misma línea de orden en la recepción.',
            'lines.*.purchase_order_item_id.exists' => 'Una línea de orden indicada no existe o no pertenece a su negocio.',
            'lines.*.received_quantity.required' => 'Cada línea debe indicar la cantidad recibida.',
            'lines.*.received_quantity.gt' => 'La cantidad recibida debe ser mayor que cero.',
            'lines.*.invoiced_unit_cost.required' => 'Cada línea debe indicar el costo facturado.',
            'lines.*.invoiced_unit_cost.min' => 'El costo facturado no puede ser negativo.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'purchase_order_id' => 'orden de compra',
            'warehouse_id' => 'bodega receptora',
            'supplier_invoice_number' => 'número de factura',
            'supplier_invoice_total' => 'total de la factura',
            'tolerance' => 'tolerancia',
            'lines' => 'líneas de recepción',
            'lines.*.purchase_order_item_id' => 'línea de orden',
            'lines.*.received_quantity' => 'cantidad recibida',
            'lines.*.invoiced_unit_cost' => 'costo facturado',
        ];
    }
}
