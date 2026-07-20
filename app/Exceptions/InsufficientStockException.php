<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;

class InsufficientStockException extends Exception
{
    public function __construct(string $sku = '', string $warehouse = '')
    {
        parent::__construct("Stock insuficiente para el producto [{$sku}] en la bodega [{$warehouse}].");
    }

    public function render(): JsonResponse
    {
        return response()->json(['message' => $this->getMessage(), 'error' => 'INSUFFICIENT_STOCK'], 409);
    }
}
