<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Recipe;

use App\Enums\ProductType;
use App\Http\Requests\BaseTenantRequest;
use App\Http\Requests\Api\V1\Recipe\Concerns\ResolvesCompoundRoute;
use App\Models\Product;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;


final class StoreRecipeLineRequest extends BaseTenantRequest
{
    use ResolvesCompoundRoute;

    public function authorize(): bool
    {
        $compound = $this->compound();

        return $compound !== null
            && ($this->user()?->can('manageRecipe', $compound) ?? false);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            // ingredient_id: del tenant y NO servicio. El ciclo indirecto
            // lo valida RecipeService por DFS. La auto-composición directa
            // se comprueba en after(), es barata.
            'ingredient_id' => [
                'required',
                'integer',
                Rule::exists('products', 'id')
                    ->where('business_id', $this->businessId())
                    ->where(fn ($q) => $q->where('type', '!=', ProductType::Service->value))
                    ->whereNull('deleted_at'),
            ],

            'quantity' => ['required', 'numeric', 'decimal:0,3', 'gt:0'],

            'unit_id' => ['required', 'integer', $this->tenantExists('units_of_measure', 'id', excludeTrashed: false)],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                // El compuesto de la ruta debe existir, ser del tenant y compound.
                if (! $this->compoundIsValid()) {
                    $validator->errors()->add(
                        'compound',
                        'El producto indicado no existe, no pertenece a su negocio o no es un producto compuesto.'
                    );

                    return;
                }

                // chk_recipe_no_self: un compuesto no puede ser su propio insumo.
                if ((int) $this->input('ingredient_id') === $this->compoundId()) {
                    $validator->errors()->add(
                        'ingredient_id',
                        'Un producto compuesto no puede incluirse a sí mismo como insumo.'
                    );
                }

                // UQ(compound_id, ingredient_id): línea duplicada.
                $duplicada = Product::query()
                    ->whereKey($this->input('ingredient_id'))
                    ->whereHas('usedInRecipes', fn ($q) => $q->where('compound_id', $this->compoundId()))
                    ->exists();

                if ($duplicada) {
                    $validator->errors()->add(
                        'ingredient_id',
                        'Este insumo ya forma parte de la receta. Edite la línea existente en lugar de duplicarla.'
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
            'ingredient_id.required' => 'Debe indicar el insumo que compone el producto.',
            'ingredient_id.integer'  => 'El insumo seleccionado no es válido.',
            'ingredient_id.exists'   => 'El insumo no existe, es un servicio o no pertenece a su negocio.',
            'quantity.required'      => 'La cantidad del insumo es obligatoria.',
            'quantity.numeric'       => 'La cantidad debe ser un valor numérico.',
            'quantity.decimal'       => 'La cantidad admite un máximo de tres decimales.',
            'quantity.gt'            => 'La cantidad debe ser mayor que cero.',
            'unit_id.required'       => 'La unidad de medida del insumo es obligatoria.',
            'unit_id.exists'         => 'La unidad de medida no existe o no pertenece a su negocio.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'ingredient_id' => 'insumo',
            'quantity'      => 'cantidad',
            'unit_id'       => 'unidad de medida',
        ];
    }
}

