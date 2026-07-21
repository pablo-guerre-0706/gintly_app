<?php

namespace App\Enums;

enum AnomalyRuleCode: string {
    case DescuadreCaja       = 'descuadre_caja';
    case FaltanteInventario  = 'faltante_inventario';
    case Discrepancia3Way    = 'discrepancia_3way';
    case CuentaVencida       = 'cuenta_vencida';
    case OmisionRegistro     = 'omision_registro';
    case VentaSinSesion      = 'venta_sin_sesion';
}
