<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\StockTransfer;

use App\Http\Requests\BaseTenantRequest;
use App\Models\StockTransfer;
use Illuminate\Validation\Validator;


final class StoreStockTransferRequest extends BaseTenantRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', StockTransfer::class) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'from_warehouse_id' => ['required', 'integer', $this->tenantExists('warehouses')->where('is_active', true)],

            'to_warehouse_id' => [
                'required',
                'integer',
                'different:from_warehouse_id',
                $this->tenantExists('warehouses')->where('is_active', true),
            ],

            'notes' => ['nullable', 'string', 'max:500'],

            // Al menos una línea; sin ítems no hay traspaso.
            'items'   => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'distinct', $this->tenantExists('products')],
            'items.*.quantity'   => ['required', 'numeric', 'decimal:0,3', 'gt:0'],
        ];
    }

    /**
     * Refuerzo de dominio de chk_transfer_diff_warehouse. `different` ya lo
     * cubre; este after() da el mensaje de negocio si el orden de reglas cambia.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($this->filled('from_warehouse_id')
                    && (int) $this->input('from_warehouse_id') === (int) $this->input('to_warehouse_id')
                ) {
                    $validator->errors()->add(
                        'to_warehouse_id',
                        'La bodega destino debe ser distinta de la bodega origen.'
                    );
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'from_warehouse_id.required' => 'La bodega origen es obligatoria.',
            'from_warehouse_id.exists'   => 'La bodega origen no existe, está inactiva o no pertenece a su negocio.',
            'to_warehouse_id.required'   => 'La bodega destino es obligatoria.',
            'to_warehouse_id.different'  => 'La bodega destino debe ser distinta de la bodega origen.',
            'to_warehouse_id.exists'     => 'La bodega destino no existe, está inactiva o no pertenece a su negocio.',
            'notes.max'                  => 'Las observaciones no pueden exceder los 500 caracteres.',
            'items.required'             => 'Debe incluir al menos un producto en el traspaso.',
            'items.min'                  => 'Debe incluir al menos un producto en el traspaso.',
            'items.*.product_id.required' => 'Cada línea debe indicar el producto.',
            'items.*.product_id.distinct' => 'No repita el mismo producto en varias líneas del traspaso.',
            'items.*.product_id.exists'   => 'Un producto indicado no existe o no pertenece a su negocio.',
            'items.*.quantity.required'   => 'Cada línea debe indicar la cantidad.',
            'items.*.quantity.decimal'    => 'La cantidad admite un máximo de tres decimales.',
            'items.*.quantity.gt'         => 'La cantidad de cada línea debe ser mayor que cero.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'from_warehouse_id'  => 'bodega origen',
            'to_warehouse_id'    => 'bodega destino',
            'notes'              => 'observaciones',
            'items'              => 'productos',
            'items.*.product_id' => 'producto',
            'items.*.quantity'   => 'cantidad',
        ];
    }
}
