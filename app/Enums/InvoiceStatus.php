<?php

namespace App\Enums;

enum InvoiceStatus: string {
    case Emitida = 'emitida';  case Anulada = 'anulada';
    public function isVoided(): bool { return $this === self::Anulada; }
}
