<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * HTTP 409 · La Idempotency-Key corresponde a un checkout ya creado que VENCIÓ sin completarse. No se sobrescribe
 * ese intento (se conserva su correlación histórica: un pago tardío de esa URL aún debe poder correlacionarse);
 * para contratar de nuevo se exige una Idempotency-Key NUEVA.
 */
final class CheckoutKeyExpiredException extends RuntimeException
{
    public function __construct(string $message = 'Ese identificador de contratación venció. Inicie una contratación nueva con otra clave.')
    {
        parent::__construct($message);
    }

    public function render(): JsonResponse
    {
        return response()->json(['message' => $this->getMessage(), 'code' => 'CHECKOUT_KEY_EXPIRED'], 409);
    }
}
