<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\SaleItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SaleItem
 *
 * Línea devolvible (vista MÍNIMA para preparar la devolución). Cantidades como string con escala 3.
 * No expone precios, impuestos ni costos: solo lo necesario para elegir qué y cuánto devolver.
 */
final class EligibleReturnLineResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'sale_item_id' => $this->id,
            'product_id' => $this->product_id,
            'product_name' => $this->whenLoaded('product', fn () => $this->product?->name) ?? $this->description,
            'delivered_quantity' => (string) $this->dispatched_quantity,
            'returned_quantity' => (string) $this->returned_quantity,
            'returnable_quantity' => $this->returnableQuantity(),
        ];
    }
}
