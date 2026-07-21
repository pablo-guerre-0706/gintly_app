<?php

namespace App\Enums;

enum ThresholdType: string {
    case Monto = 'monto';  case Porcentaje = 'porcentaje';  case Cantidad = 'cantidad';  case Tiempo = 'tiempo';
}
