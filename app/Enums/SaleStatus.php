<?php

namespace App\Enums;

enum SaleStatus: string {
    case Abierta = 'abierta';  case Confirmada = 'confirmada';
    case Facturada = 'facturada';  case Anulada = 'anulada';
    public function canInvoice(): bool { return $this === self::Confirmada; }
    public function canEditItems(): bool { return $this === self::Abierta; }
}
