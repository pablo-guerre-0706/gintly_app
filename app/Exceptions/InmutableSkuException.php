<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;

class ImmutableSkuException extends Exception
{
    public function __construct(
        string $message = 'El SKU es inmutable: el producto ya tiene transacciones asociadas.'
    ) {
        parent::__construct($message);
    }

    public function render(): JsonResponse
    {
        return response()->json(['message' => $this->getMessage(), 'error' => 'IMMUTABLE_SKU'], 422);
    }
}
