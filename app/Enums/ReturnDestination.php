<?php

namespace App\Enums;

enum ReturnDestination: string {
    case Reingreso = 'reingreso';   // apto: vuelve al stock, recalcula costo promedio
    case Merma     = 'merma';       // dañado: va a pérdidas
    public function reentersStock(): bool { return $this === self::Reingreso; }
}
