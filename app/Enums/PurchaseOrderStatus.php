<?php

namespace App\Enums;

enum PurchaseOrderStatus: string
{
    case Borrador  = 'borrador';
    case Emitida   = 'emitida';
    case Parcial   = 'parcial';
    case Recibida  = 'recibida';
    case Cancelada = 'cancelada';

    /** Estados que aún admiten recepción de mercancía. */
    public function canReceive(): bool
    {
        return in_array($this, [self::Emitida, self::Parcial], true);
    }
}