<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * HTTP 409 · Ya existe un checkout ABIERTO del negocio que todavía puede completarse (pagable) para OTRA
 * selección. Como la API de Lemon Squeezy no ofrece un endpoint para invalidar un checkout ya creado, no se
 * abre una segunda contratación mientras la anterior siga vigente: así nunca hay dos URLs pagables a la vez.
 * El cliente debe completar la contratación abierta o esperar a que su expires_at venza.
 */
final class CheckoutInProgressException extends RuntimeException
{
    public function __construct(string $message = 'Ya tiene una contratación abierta que puede completarse. Complétela o espere a que venza antes de iniciar otra.')
    {
        parent::__construct($message);
    }

    public function render(): JsonResponse
    {
        return response()->json(['message' => $this->getMessage(), 'code' => 'CHECKOUT_IN_PROGRESS'], 409);
    }
}
