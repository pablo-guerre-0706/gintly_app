<?php

namespace App\Enums;

enum InvoicePaymentStatus: string {
    case Pagada = 'pagada';  case Parcial = 'parcial';  case Pendiente = 'pendiente';
}
