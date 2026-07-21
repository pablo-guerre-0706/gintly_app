<?php

namespace App\Enums;

enum CreditNoteResolutionType: string {
    case ReembolsoEfectivo = 'reembolso_efectivo';
    case NotaCreditoSaldo  = 'nota_credito_saldo';
    case ReduccionCxc      = 'reduccion_cxc';
}