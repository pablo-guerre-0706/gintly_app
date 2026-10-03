<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;

class NoActiveCashSessionException extends Exception
{
    public function __construct(string $message = 'No existe una sesión de caja activa para registrar la operación.')
    {
        parent::__construct($message);
    }

    public function render(): JsonResponse
    {
        return response()->json(['message' => $this->getMessage(), 'error' => 'NO_ACTIVE_CASH_SESSION'], 409);
    }

    public static function forMovement(int $sessionId): self
    {
        return new self("No hay una sesión de caja abierta (sesión {$sessionId}) para registrar el movimiento.");
    }

    public static function forClosing(int $sessionId): self
    {
        return new self("La sesión de caja {$sessionId} no está abierta y no puede cerrarse.");
    }

    /** ERR-08B: abono en efectivo sin sesión de caja activa. */
    public static function forCreditPayment(): self
    {
        return new self('No hay una sesión de caja activa para recibir el cobro en efectivo.');
    }
}
