<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Product;

use App\Enums\ProductType;
use App\Http\Requests\BaseTenantRequest;
use App\Models\Product;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;


final class UpdateProductRequest extends BaseTenantRequest
{
    public function authorize(): bool
    {
        $target = $this->route('product');

        return $this->user()?->can('update', $target instanceof Product ? $target : Product::class) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'sku' => [
                'sometimes',
                'required',
                'string',
                'max:60',
                $this->tenantUnique('products', 'sku', excludeTrashed: false)
                    ->ignore($this->routeId('product')),
            ],

            'name' => ['sometimes', 'required', 'string', 'max:160'],

            'type' => ['sometimes', 'required', Rule::enum(ProductType::class)],

            'category_id' => ['sometimes', 'required', 'integer', $this->tenantExists('categories')],

            'brand_id' => ['sometimes', 'nullable', 'integer', $this->tenantExists('brands')],

            'unit_id' => ['sometimes', 'required', 'integer', $this->tenantExists('units_of_measure', 'id', excludeTrashed: false)],

            'sale_price' => ['sometimes', 'numeric', 'decimal:0,2', 'min:0'],
            'cost'       => ['sometimes', 'numeric', 'decimal:0,2', 'min:0'],

            'tracks_inventory' => ['sometimes', 'boolean'],
            'is_taxable'       => ['sometimes', 'boolean'],
            'is_active'        => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Guardián de SKU inmutable.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if (! $this->filled('sku')) {
                    return;
                }

                $product = $this->route('product');

                if (! $product instanceof Product) {
                    return;
                }

                $skuNuevo = mb_strtoupper(trim((string) $this->input('sku')));

                if ($skuNuevo !== $product->sku && $product->hasTransactions()) {
                    $validator->errors()->add(
                        'sku',
                        'El código SKU no puede modificarse: el producto ya tiene transacciones asociadas.'
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
            'sku.required'          => 'El código SKU no puede quedar vacío.',
            'sku.max'               => 'El código SKU no puede exceder los 60 caracteres.',
            'sku.unique'            => 'Ya existe otro producto con este código SKU en el negocio.',
            'name.required'         => 'El nombre del producto no puede quedar vacío.',
            'name.max'              => 'El nombre no puede exceder los 160 caracteres.',
            'type.enum'             => 'El tipo de producto debe ser simple, compuesto o servicio.',
            'category_id.required'  => 'La categoría no puede quedar vacía.',
            'category_id.exists'    => 'La categoría seleccionada no existe o no pertenece a su negocio.',
            'brand_id.exists'       => 'La marca seleccionada no existe o no pertenece a su negocio.',
            'unit_id.required'      => 'La unidad de medida no puede quedar vacía.',
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
            $merge['sku'] = mb_strtoupper(trim($this->sku));
        }

        if (is_string($this->name)) {
            $merge['name'] = trim($this->name);
        }

        if ($this->input('type') === ProductType::Service->value) {
            $merge['tracks_inventory'] = false;
        }

        if ($merge !== []) {
            $this->merge($merge);
        }
    }
}

