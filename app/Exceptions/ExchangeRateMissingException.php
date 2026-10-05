<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Enums\Currency;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * HTTP 422. No hay un tipo de cambio vigente para una moneda extranjera al momento de la operación.
 * Rechazo CONTROLADO: una operación en USD no puede asentarse sin una tasa snapshot administrada; no se
 * inventa ni se asume. Se auto-renderiza con `code`, coherente con el resto del contrato.
 */
final class ExchangeRateMissingException extends RuntimeException
{
    private function __construct(string $message)
    {
        parent::__construct($message);
    }

    public static function forCurrency(Currency $currency): self
    {
        return new self(
            "No hay un tipo de cambio vigente para {$currency->value}. "
            .'Configure la tasa (ROL-01/ROL-02) antes de operar efectivo en esa moneda.'
        );
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'code'    => 'EXCHANGE_RATE_MISSING',
        ], 422);
    }
}
