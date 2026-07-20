<?php
// Compartido con MOD-06/07/08/10
namespace App\Enums;

enum PaymentMethod: string
{
    case Efectivo      = 'efectivo';
    case Transferencia = 'transferencia';
    case Tarjeta       = 'tarjeta';

    /** Solo el efectivo vive en la gaveta física → solo él cuenta en el arqueo. */
    public function affectsCashDrawer(): bool
    {
        return $this === self::Efectivo;
    }
}
