<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\PurchaseOrder;

use App\Enums\SupplierStatus;
use App\Http\Requests\BaseTenantRequest;
use App\Models\PurchaseOrder;
use Illuminate\Validation\Rule;


final class StorePurchaseOrderRequest extends BaseTenantRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', PurchaseOrder::class) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            // approved: exists acotado a status=aprobado. ERR-04B lo confirma
            // el service; esto es la primera barrera legible.
            'supplier_id' => [
                'required',
                'integer',
                Rule::exists('suppliers', 'id')
                    ->where('business_id', $this->businessId())
                    ->where('status', SupplierStatus::Aprobado->value)
                    ->whereNull('deleted_at'),
            ],

            'branch_id' => ['required', 'integer', $this->tenantExists('branches')],

            'ordered_at' => ['required', 'date_format:Y-m-d'],

            'notes' => ['nullable', 'string', 'max:500'],

            'items'   => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'distinct', $this->tenantExists('products')],
            'items.*.ordered_quantity' => ['required', 'numeric', 'decimal:0,3', 'gt:0'],
            'items.*.agreed_unit_cost' => ['required', 'numeric', 'decimal:0,4', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'supplier_id.required' => 'El proveedor es obligatorio.',
            'supplier_id.exists'   => 'El proveedor no existe, no está aprobado o no pertenece a su negocio.',
            'branch_id.required'   => 'La sucursal es obligatoria.',
            'branch_id.exists'     => 'La sucursal no existe o no pertenece a su negocio.',
            'ordered_at.required'  => 'La fecha de la orden es obligatoria.',
            'ordered_at.date_format' => 'La fecha de la orden debe expresarse en formato AAAA-MM-DD.',
            'notes.max'            => 'Las observaciones no pueden exceder los 500 caracteres.',
            'items.required'       => 'Debe incluir al menos un producto en la orden.',
            'items.min'            => 'Debe incluir al menos un producto en la orden.',
            'items.*.product_id.required' => 'Cada línea debe indicar el producto.',
            'items.*.product_id.distinct' => 'No repita el mismo producto en varias líneas de la orden.',
            'items.*.product_id.exists'   => 'Un producto indicado no existe o no pertenece a su negocio.',
            'items.*.ordered_quantity.required' => 'Cada línea debe indicar la cantidad ordenada.',
            'items.*.ordered_quantity.decimal'  => 'La cantidad admite un máximo de tres decimales.',
            'items.*.ordered_quantity.gt'       => 'La cantidad ordenada debe ser mayor que cero.',
            'items.*.agreed_unit_cost.required' => 'Cada línea debe indicar el costo pactado.',
            'items.*.agreed_unit_cost.decimal'  => 'El costo admite un máximo de cuatro decimales.',
            'items.*.agreed_unit_cost.min'      => 'El costo pactado no puede ser negativo.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'supplier_id' => 'proveedor',
            'branch_id'   => 'sucursal',
            'ordered_at'  => 'fecha de la orden',
            'notes'       => 'observaciones',
            'items'       => 'productos',
            'items.*.product_id'       => 'producto',
            'items.*.ordered_quantity' => 'cantidad ordenada',
            'items.*.agreed_unit_cost' => 'costo pactado',
        ];
    }
}
