<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Product;

use App\Enums\ProductType;
use App\Enums\TaxClass;
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
            // Cambiar la clase fiscal afecta SOLO las líneas futuras; las ya congeladas
            // no se tocan. is_taxable es ALIAS DE ENTRADA DEPRECADO: no se persiste, solo
            // se traduce (prepareForValidation) y se verifica su coherencia (after()).
            'tax_class'        => ['sometimes', Rule::enum(TaxClass::class)],
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
            // Coherencia del alias deprecado is_taxable con tax_class (si llegan ambos).
            function (Validator $validator): void {
                if (! $this->has('is_taxable') || ! $this->filled('tax_class')) {
                    return;
                }

                $taxClass = TaxClass::tryFrom((string) $this->input('tax_class'));
                if ($taxClass === null) {
                    return;
                }

                if (($taxClass !== TaxClass::Exempt) !== $this->boolean('is_taxable')) {
                    $validator->errors()->add(
                        'is_taxable',
                        'is_taxable (alias deprecado) contradice tax_class: is_taxable=false equivale a tax_class=exempt, y true a una clase gravada.'
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
            'tax_class.enum'        => 'La clase fiscal debe ser tasa general, reducida, tasa cero o exento.',
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
            'tax_class'        => 'clase fiscal',
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

        // Alias deprecado: si llega is_taxable sin tax_class, se traduce
        // (true→standard, false→exempt). Sin default en update (tax_class es 'sometimes').
        if (! $this->filled('tax_class') && $this->has('is_taxable')) {
            $merge['tax_class'] = $this->boolean('is_taxable')
                ? TaxClass::Standard->value
                : TaxClass::Exempt->value;
        }

        if ($merge !== []) {
            $this->merge($merge);
        }
    }
}

