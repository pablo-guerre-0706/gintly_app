<?php

namespace App\Enums;

enum GoodsReceiptMatchStatus: string
{
    case Ok           = 'ok';
    case Discrepancia = 'discrepancia';
    case Bloqueada    = 'bloqueada';

    /** Solo 'ok' habilita el ingreso a inventario (RF-04-03/04). */
    public function allowsInventoryEntry(): bool
    {
        return $this === self::Ok;
    }
}