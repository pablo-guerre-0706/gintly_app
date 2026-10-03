<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Sale;

use App\Http\Requests\BaseTenantRequest;
use App\Models\Sale;

/**
 * Agrega una línea. Solo en 'abierta' (lo verifica el service bajo lock). El
 * precio, costo, gravabilidad y receta se CONGELAN en el service desde el
 * producto vivo; el request solo aporta producto, cantidad y descuento.
 */
final class StoreSaleItemRequest extends BaseTenantRequest
{
    public function authorize(): bool
    {
        $sale = $this->route('sale');

        return $sale instanceof Sale
            && ($this->user()?->can('manageItems', $sale) ?? false);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'product_id' => ['required', 'integer', $this->tenantExists('products')],
            'quantity'   => ['required', 'numeric', 'decimal:0,3', 'gt:0'],
            'discount_amount' => ['sometimes', 'numeric', 'decimal:0,2', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'product_id.required' => 'El producto es obligatorio.',
            'product_id.exists'   => 'El producto no existe o no pertenece a su negocio.',
            'quantity.required'   => 'La cantidad es obligatoria.',
            'quantity.decimal'    => 'La cantidad admite un máximo de tres decimales.',
            'quantity.gt'         => 'La cantidad debe ser mayor que cero.',
            'discount_amount.decimal' => 'El descuento admite un máximo de dos decimales.',
            'discount_amount.min'     => 'El descuento no puede ser negativo.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'product_id'      => 'producto',
            'quantity'        => 'cantidad',
            'discount_amount' => 'descuento',
        ];
    }
}
