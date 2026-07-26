<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Product;

use App\Enums\ProductType;
use App\Http\Requests\BaseTenantRequest;
use App\Models\Product;
use Illuminate\Validation\Rule;

final class StoreProductRequest extends BaseTenantRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Product::class) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            // sku con índice plano (sin candado parcial). excludeTrashed=false
            // porque el SKU de un producto borrado sigue ocupado perpetuamente.
            'sku' => [
                'required',
                'string',
                'max:60',
                $this->tenantUnique('products', 'sku', excludeTrashed: false),
            ],

            'name' => ['required', 'string', 'max:160'],

            'type' => ['required', Rule::enum(ProductType::class)],

            'category_id' => ['required', 'integer', $this->tenantExists('categories')],

            'brand_id' => ['nullable', 'integer', $this->tenantExists('brands')],

            // Tabla units_of_measure, sin softDeletes → excludeTrashed=false.
            'unit_id' => ['required', 'integer', $this->tenantExists('units_of_measure', 'id', excludeTrashed: false)],

            // Dinero como decimal de escala fija, nunca numeric a secas.
            'sale_price' => ['sometimes', 'numeric', 'decimal:0,2', 'min:0'],
            'cost'       => ['sometimes', 'numeric', 'decimal:0,2', 'min:0'],

            // tracks_inventory se acepta pero se coacciona en prepareForValidation
            // cuando type=service. No se rechaza jamás.
            'tracks_inventory' => ['sometimes', 'boolean'],
            'is_taxable'       => ['sometimes', 'boolean'],
            'is_active'        => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'sku.required'          => 'El código SKU es obligatorio.',
            'sku.max'               => 'El código SKU no puede exceder los 60 caracteres.',
            'sku.unique'            => 'Ya existe un producto con este código SKU en el negocio. El SKU es único y no se reutiliza, ni siquiera tras dar de baja el producto.',
            'name.required'         => 'El nombre del producto es obligatorio.',
            'name.max'              => 'El nombre no puede exceder los 160 caracteres.',
            'type.enum'             => 'El tipo de producto debe ser simple, compuesto o servicio.',
            'category_id.required'  => 'La categoría es obligatoria.',
            'category_id.exists'    => 'La categoría seleccionada no existe o no pertenece a su negocio.',
            'brand_id.exists'       => 'La marca seleccionada no existe o no pertenece a su negocio.',
            'unit_id.required'      => 'La unidad de medida es obligatoria.',
            'unit_id.exists'        => 'La unidad de medida seleccionada no existe o no pertenece a su negocio.',
            'sale_price.numeric'    => 'El precio de venta debe ser un valor numérico.',
            'sale_price.decimal'    => 'El precio de venta admite un máximo de dos decimales.',
            'sale_price.min'        => 'El precio de venta no puede ser negativo.',
            'cost.numeric'          => 'El costo debe ser un valor numérico.',
            'cost.decimal'          => 'El costo admite un máximo de dos decimales.',
            'cost.min'              => 'El costo no puede ser negativo.',
            'tracks_inventory.boolean' => 'El control de inventario debe ser verdadero o falso.',
            'is_taxable.boolean'    => 'El indicador de gravabilidad debe ser verdadero o falso.',
            'is_active.boolean'     => 'El estado del producto debe ser verdadero o falso.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'sku'              => 'código SKU',
            'name'             => 'nombre',
            'type'             => 'tipo',
            'category_id'      => 'categoría',
            'brand_id'         => 'marca',
            'unit_id'          => 'unidad de medida',
            'sale_price'       => 'precio de venta',
            'cost'             => 'costo',
            'tracks_inventory' => 'controla inventario',
            'is_taxable'       => 'gravable',
            'is_active'        => 'estado',
        ];
    }

    protected function prepareForValidation(): void
    {
        $merge = [];

        if (is_string($this->sku)) {
            // SKU normalizado a mayúsculas: evita duplicados por diferencia de caja.
            $merge['sku'] = mb_strtoupper(trim($this->sku));
        }

        if (is_string($this->name)) {
            $merge['name'] = trim($this->name);
        }

        // H-16: coerción de servicio. Si type=service, tracks_inventory=false,
        // con independencia de lo enviado. El modelo lo reafirma en saving().
        if ($this->input('type') === ProductType::Service->value) {
            $merge['tracks_inventory'] = false;
        }

        if ($merge !== []) {
            $this->merge($merge);
        }
    }
}
