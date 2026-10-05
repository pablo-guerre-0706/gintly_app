<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\StockLevel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * MOD-03 (microcierre) · AVISO operativo de mínimo (derivado, no es una anomalía del catálogo cerrado).
 * Se emite cuando el DISPONIBLE (quantity − reserved) alcanza o cae bajo el mínimo CONFIGURADO. Identifica
 * producto, bodega, disponible y mínimo, y adjunta los datos para INICIAR (no crear) una orden de compra.
 *
 * @mixin StockLevel
 */
final class LowStockAlertResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $available  = (string) $this->available;
        $min        = (string) $this->min_stock;
        $suggested  = $this->suggestedReplenishment();

        return [
            'product_id'      => $this->product_id,
            'sku'             => $this->product?->sku,
            'product_name'    => $this->product?->name,
            'unit'            => $this->product?->unit?->abbreviation,
            'warehouse_id'    => $this->warehouse_id,
            'warehouse_name'  => $this->warehouse?->name,
            'branch_id'       => $this->warehouse?->branch_id,
            'available'       => $available,
            'min_stock'       => $min,
            'max_stock'       => $this->max_stock,
            // Datos mínimos para INICIAR una orden de compra reutilizando el flujo existente (no se crea aquí).
            'replenishment'   => [
                'product_id'         => $this->product_id,
                'unit'               => $this->product?->unit?->abbreviation,
                'branch_id'          => $this->warehouse?->branch_id,
                'warehouse_id'       => $this->warehouse_id,
                'available'          => $available,
                'min_stock'          => $min,
                'suggested_quantity' => $suggested,
            ],
        ];
    }

    /**
     * Cantidad sugerida para volver al objetivo: a max_stock si está configurado, si no al mínimo.
     * Nunca negativa. bcmath escala 3 (unidad del producto; no se mezcla con otras unidades).
     */
    private function suggestedReplenishment(): string
    {
        $target = $this->max_stock !== null ? (string) $this->max_stock : (string) $this->min_stock;
        $gap    = bcsub($target, (string) $this->available, 3);

        return bccomp($gap, '0', 3) > 0 ? $gap : '0.000';
    }
}
