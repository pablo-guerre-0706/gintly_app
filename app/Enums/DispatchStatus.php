<?php

namespace App\Enums;

enum DispatchStatus: string
{
    case Registrado = 'registrado';
    case Revertido  = 'revertido';

    public function isActive(): bool
    {
        return $this === self::Registrado;
    }
}
