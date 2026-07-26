<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;


// HTTP 409. Se intentó aplicar o justificar un conteo que ya no está abierto,
// o completar/cancelar un traspaso que ya no está pendiente.
final class InvalidCountStateException extends RuntimeException
{
    public static function countNotOpen(int $countId): self
    {
        return new self("El conteo {$countId} ya fue procesado y no admite nuevas acciones.");
    }

    public static function transferNotPending(int $transferId): self
    {
        return new self("El traspaso {$transferId} ya no está pendiente y no admite esta acción.");
    }
}
