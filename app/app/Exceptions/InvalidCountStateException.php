<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;


// HTTP 409. Se intentó aplicar o justificar un conteo que ya no está abierto,
// o completar/cancelar un traspaso que ya no está pendiente.
final class InvalidCountStateException extends RuntimeException
{
    // Self-render: sin él, el 409 del contrato (apply/justify/complete/cancel
    // sobre un estado inválido) degradaba a 500 por falta de mapeo.
    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'error'   => 'INVALID_STATE',
            'message' => $this->getMessage(),
        ], 409);
    }

    public static function countNotOpen(int $countId): self
    {
        return new self("El conteo {$countId} ya fue procesado y no admite nuevas acciones.");
    }

    public static function transferNotPending(int $transferId): self
    {
        return new self("El traspaso {$transferId} ya no está pendiente y no admite esta acción.");
    }

    // Traspaso pendiente sin líneas persistidas (dato previo a la opción A).
    // No se confirma con líneas inventadas: se rechaza de forma controlada.
    public static function transferHasNoLines(int $transferId): self
    {
        return new self("El traspaso {$transferId} no tiene líneas registradas y no puede completarse.");
    }
}
