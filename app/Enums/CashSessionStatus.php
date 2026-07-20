<?php

namespace App\Enums;

enum CashSessionStatus: string
{
    case Abierta     = 'abierta';
    case Cerrada     = 'cerrada';
    case Descuadrada = 'descuadrada';   // cerrada con diferencia ≠ 0, pendiente de validación admin

    public function isOpen(): bool
    {
        return $this === self::Abierta;
    }
}
