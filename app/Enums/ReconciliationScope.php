<?php

namespace App\Enums;

enum ReconciliationScope: string {
    case Caja = 'caja';  case InventarioBodega = 'inventario_bodega';
    case Compras3Way = 'compras_3way';  case Integral = 'integral';
}
