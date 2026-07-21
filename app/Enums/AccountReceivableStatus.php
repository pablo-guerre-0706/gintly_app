<?php

namespace App\Enums;

enum AccountReceivableStatus: string
{
    case Pendiente = 'pendiente';
    case Parcial   = 'parcial';
    case Pagada    = 'pagada';
    case Vencida   = 'vencida';

    /** Cuentas que aún suman a la exposición de crédito del cliente (RF-08-02). */
    public function countsInExposure(): bool
    {
        return in_array($this, [self::Pendiente, self::Parcial, self::Vencida], true);
    }

    public function isSettled(): bool
    {
        return $this === self::Pagada;
    }
}
