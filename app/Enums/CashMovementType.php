<?php

namespace App\Enums;

enum CashMovementType: string
{
    case Ingreso = 'ingreso';
    case Egreso  = 'egreso';

    /** +1 suma a la gaveta, -1 resta. Usado en el cálculo del esperado. */
    public function signedFactor(): int
    {
        return $this === self::Ingreso ? 1 : -1;
    }
}
