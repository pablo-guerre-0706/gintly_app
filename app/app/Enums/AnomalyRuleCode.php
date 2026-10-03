<?php

declare(strict_types=1);

namespace App\Enums;

enum AnomalyRuleCode: string
{
    case DescuadreCaja       = 'descuadre_caja';
    case FaltanteInventario  = 'faltante_inventario';
    case Discrepancia3Way    = 'discrepancia_3way';
    case CuentaVencida       = 'cuenta_vencida';
    case OmisionRegistro     = 'omision_registro';
    case VentaSinSesion      = 'venta_sin_sesion';

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_map(static fn (self $c): string => $c->value, self::cases());
    }

    public function label(): string
    {
        return match ($this) {
            self::DescuadreCaja      => 'Descuadre de caja',
            self::FaltanteInventario => 'Faltante de inventario',
            self::Discrepancia3Way   => 'Discrepancia 3-way',
            self::CuentaVencida      => 'Cuenta por cobrar vencida',
            self::OmisionRegistro    => 'Omisión de registro',
            self::VentaSinSesion     => 'Venta sin sesión de caja',
        };
    }
}
