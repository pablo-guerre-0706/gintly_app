<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\ProductRecipe;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ProductRecipe
 */
final class RecipeLineResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->id,
            'compound_id'   => $this->compound_id,
            'ingredient_id' => $this->ingredient_id,
            'quantity'      => $this->quantity,
            'unit_id'       => $this->unit_id,
            'ingredient'    => new ProductResource($this->whenLoaded('ingredient')),
            'unit'          => new UnitResource($this->whenLoaded('unit')),
        ];
    }
}

