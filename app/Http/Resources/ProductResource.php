<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Product
 */
final class ProductResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'sku'         => $this->sku,
            'name'        => $this->name,
            'type'        => $this->type->value,
            'type_label'  => $this->type->label(),
            'category_id' => $this->category_id,
            'brand_id'    => $this->brand_id,
            'unit_id'     => $this->unit_id,
            // decimal:2 → el cast ya entrega string con escala fija.
            'sale_price'  => $this->sale_price,
            'cost'        => $this->cost,
            'tracks_inventory' => $this->tracks_inventory,
            'is_taxable'  => $this->is_taxable,
            'is_active'   => $this->is_active,
            'category'    => new CategoryResource($this->whenLoaded('category')),
            'brand'       => new BrandResource($this->whenLoaded('brand')),
            'unit'        => new UnitResource($this->whenLoaded('unit')),
            'created_at'  => $this->created_at?->toIso8601String(),
            'updated_at'  => $this->updated_at?->toIso8601String(),
        ];
    }
}
