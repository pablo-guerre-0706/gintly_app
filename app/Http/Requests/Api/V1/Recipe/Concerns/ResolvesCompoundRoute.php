<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Recipe\Concerns;

use App\Enums\ProductType;
use App\Models\Product;
use App\Models\ProductRecipe;


// Resolución del binding anidado /products/{compound}/recipe/{line}.
trait ResolvesCompoundRoute
{
    protected function compound(): ?Product
    {
        $compound = $this->route('compound');

        return $compound instanceof Product ? $compound : null;
    }

    protected function compoundId(): ?int
    {
        return $this->compound()?->getKey();
    }

    protected function recipeLine(): ?ProductRecipe
    {
        $line = $this->route('line');

        return $line instanceof ProductRecipe ? $line : null;
    }

    // El compuesto existe, pertenece al negocio y es de tipo compound.
    protected function compoundIsValid(): bool
    {
        $compound = $this->compound();

        return $compound !== null
            && (int) $compound->business_id === $this->businessId()
            && $compound->type === ProductType::Compound;
    }

    // La línea pertenece al compuesto de la ruta.
    protected function lineBelongsToCompound(): bool
    {
        $line = $this->recipeLine();

        return $line !== null
            && $this->compoundId() !== null
            && (int) $line->compound_id === $this->compoundId();
    }
}
