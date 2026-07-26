<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\PhysicalCount;

use App\Http\Requests\BaseTenantRequest;
use App\Models\PhysicalCount;


final class StorePhysicalCountRequest extends BaseTenantRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', PhysicalCount::class) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            // Bodega activa: no se cuenta contra una bodega desactivada.
            'warehouse_id' => ['required', 'integer', $this->tenantExists('warehouses')->where('is_active', true)],

            'product_id' => ['required', 'integer', $this->tenantExists('products')],

            // counted_quantity escala 3, >= 0 (una existencia contada de 0 es
            // válida: significa que no quedó nada).
            'counted_quantity' => ['required', 'numeric', 'decimal:0,3', 'min:0'],

            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'warehouse_id.required'     => 'La bodega es obligatoria.',
            'warehouse_id.exists'       => 'La bodega no existe, está inactiva o no pertenece a su negocio.',
            'product_id.required'       => 'El producto es obligatorio.',
            'product_id.exists'         => 'El producto no existe o no pertenece a su negocio.',
            'counted_quantity.required' => 'La cantidad contada es obligatoria.',
            'counted_quantity.numeric'  => 'La cantidad contada debe ser un valor numérico.',
            'counted_quantity.decimal'  => 'La cantidad contada admite un máximo de tres decimales.',
            'counted_quantity.min'      => 'La cantidad contada no puede ser negativa.',
            'notes.max'                 => 'Las observaciones no pueden exceder los 500 caracteres.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'warehouse_id'     => 'bodega',
            'product_id'       => 'producto',
            'counted_quantity' => 'cantidad contada',
            'notes'            => 'observaciones',
        ];
    }
}
