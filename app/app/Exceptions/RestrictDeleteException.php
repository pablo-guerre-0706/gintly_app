<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;


// ERR-02B · HTTP 409. Un maestro con dependencias no admite borrado físico.
final class RestrictDeleteException extends RuntimeException
{
    public static function make(string $entidad, string $dependencia): self
    {
        return new self(
            "No es posible eliminar {$entidad}: existen {$dependencia} que lo referencian."
        );
    }
}

