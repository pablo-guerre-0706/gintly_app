<?php

namespace App\Enums;

enum SalesReturnStatus: string {
    case Registrada = 'registrada';  case Procesada = 'procesada';  case Anulada = 'anulada';
    public function canProcess(): bool { return $this === self::Registrada; }
}
