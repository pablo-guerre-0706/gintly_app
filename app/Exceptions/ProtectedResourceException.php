<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;


// PROTECTED_RESOURCE · HTTP 403. Se intentó modificar o eliminar un recurso protegido del sistema.
final class ProtectedResourceException extends RuntimeException
{
    public static function customer(): self
    {
        return new self('El "Consumidor Final" es un cliente protegido del sistema y no puede modificarse ni eliminarse.');
    }
}
