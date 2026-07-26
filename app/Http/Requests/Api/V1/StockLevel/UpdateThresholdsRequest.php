<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\StockLevel;

use App\Http\Requests\BaseTenantRequest;
use App\Models\StockLevel;
use Illuminate\Validation\Validator;


final class UpdateThresholdsRequest extends BaseTenantRequest
{
    public function authorize(): bool
    {
        $stockLevel = $this->resolveStockLevel();

        return $stockLevel !== null
            && ($this->user()?->can('updateThresholds', $stockLevel) ?? false);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            // Escala 3 (H-29). nullable: quitar un umbral es válido.
            'min_stock' => ['sometimes', 'nullable', 'numeric', 'decimal:0,3', 'min:0'],
            'max_stock' => ['sometimes', 'nullable', 'numeric', 'decimal:0,3', 'min:0'],
        ];
    }

    /**
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $min = $this->input('min_stock');
                $max = $this->input('max_stock');

                if ($min === null || $max === null) {
                    return;
                }

                if (bccomp((string) $min, (string) $max, 3) > 0) {
                    $validator->errors()->add(
                        'min_stock',
                        'El umbral mínimo no puede ser mayor que el máximo.'
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
            'min_stock.numeric' => 'El umbral mínimo debe ser un valor numérico.',
            'min_stock.decimal' => 'El umbral mínimo admite un máximo de tres decimales.',
            'min_stock.min'     => 'El umbral mínimo no puede ser negativo.',
            'max_stock.numeric' => 'El umbral máximo debe ser un valor numérico.',
            'max_stock.decimal' => 'El umbral máximo admite un máximo de tres decimales.',
            'max_stock.min'     => 'El umbral máximo no puede ser negativo.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'min_stock' => 'umbral mínimo',
            'max_stock' => 'umbral máximo',
        ];
    }

    public function resolveStockLevel(): ?StockLevel
    {
        $productId = (int) $this->route('product');
        $warehouseId = (int) $this->route('warehouse');

        return StockLevel::query()
            ->where('business_id', $this->businessId())
            ->where('product_id', $productId)
            ->where('warehouse_id', $warehouseId)
            ->first();
    }
}
