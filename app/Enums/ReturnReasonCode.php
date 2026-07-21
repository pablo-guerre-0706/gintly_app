<?php

namespace App\Enums;

use App\Enums\ReturnDestination;

enum ReturnReasonCode: string {
    case Vencido        = 'vencido';
    case DefectoFabrica = 'defecto_fabrica';
    case ErrorDespacho  = 'error_despacho';
    case Insatisfaccion = 'insatisfaccion';
    case Otro           = 'otro';

    /** Destino sugerido por el motivo (el servicio valida combinaciones prohibidas). */
    public function defaultDestination(): ReturnDestination
    {
        return match ($this) {
            self::Vencido, self::DefectoFabrica => ReturnDestination::Merma,
            self::ErrorDespacho, self::Insatisfaccion, self::Otro => ReturnDestination::Reingreso,
        };
    }
}
