<?php

namespace App\Enums;

enum InvoicePaymentType: string {
    case Contado = 'contado';  case Credito = 'credito';
    /** Al contado exige cobro íntegro (BR-05); a crédito admite saldo pendiente. */
    public function requiresFullPayment(): bool { return $this === self::Contado; }
}
