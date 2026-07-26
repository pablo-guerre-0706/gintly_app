<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Recipe;

use App\Http\Requests\BaseTenantRequest;
use App\Http\Requests\Api\V1\Recipe\Concerns\ResolvesCompoundRoute;
use Illuminate\Validation\Validator;


// Solo se editan cantidad y unidad. El insumo (ingredient_id) no es editable:
final class UpdateRecipeLineRequest extends BaseTenantRequest
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
            'quantity' => ['sometimes', 'required', 'numeric', 'decimal:0,3', 'gt:0'],
            'unit_id'  => ['sometimes', 'required', 'integer', $this->tenantExists('units_of_measure', 'id', excludeTrashed: false)],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if (! $this->compoundIsValid()) {
                    $validator->errors()->add(
                        'compound',
                        'El producto indicado no existe, no pertenece a su negocio o no es un producto compuesto.'
                    );

                    return;
                }

                // la línea debe pertenecer a este compuesto, no a otro.
                if (! $this->lineBelongsToCompound()) {
                    $validator->errors()->add(
                        'line',
                        'La línea de receta indicada no pertenece a este producto compuesto.'
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
            'quantity.required' => 'La cantidad del insumo no puede quedar vacía.',
            'quantity.numeric'  => 'La cantidad debe ser un valor numérico.',
            'quantity.decimal'  => 'La cantidad admite un máximo de tres decimales.',
            'quantity.gt'       => 'La cantidad debe ser mayor que cero.',
            'unit_id.required'  => 'La unidad de medida no puede quedar vacía.',
            'unit_id.exists'    => 'La unidad de medida no existe o no pertenece a su negocio.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'quantity' => 'cantidad',
            'unit_id'  => 'unidad de medida',
        ];
    }
}

