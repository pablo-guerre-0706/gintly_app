<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * HTTP 409. Conflicto al administrar una asignación Caja–Cajero: la caja ya tiene cajero activo, el cajero
 * ya tiene caja activa, o existe una sesión abierta vinculada que impide asignar/reasignar/finalizar.
 * Self-render con clave `error` (simétrico con CashSessionConflictException).
 */
final class CashAssignmentConflictException extends RuntimeException
{
    private function __construct(
        public readonly string $errorCode,
        string $message,
    ) {
        parent::__construct($message);
    }

    public static function registerAssigned(): self
    {
        return new self(
            'CASH_REGISTER_ALREADY_ASSIGNED',
            'La caja ya tiene un cajero asignado activo. Finalice la asignación actual antes de reasignar.',
        );
    }

    public static function userAssigned(): self
    {
        return new self(
            'CASHIER_ALREADY_ASSIGNED',
            'El cajero ya tiene una caja asignada activa. Finalice su asignación actual antes de reasignar.',
        );
    }

    public static function openSession(): self
    {
        return new self(
            'CASH_ASSIGNMENT_OPEN_SESSION',
            'Existe una sesión de caja abierta vinculada; no se puede asignar, reasignar ni finalizar mientras no se cierre.',
        );
    }

    public static function registerLocked(): self
    {
        return new self(
            'CASH_REGISTER_OPEN_SESSION',
            'La caja tiene una sesión abierta; no puede desactivarse ni eliminarse hasta cerrarla.',
        );
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'error'   => $this->errorCode,
            'message' => $this->getMessage(),
        ], 409);
    }
}
