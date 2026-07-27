<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Medio de pago (compartido). Solo el efectivo entra a la gaveta física y cuenta en el arqueo.
 * Transferencia y tarjeta se registran pero no afectan el efectivo esperado.
 */
enum PaymentMethod: string
{
    case Efectivo      = 'efectivo';
    case Transferencia = 'transferencia';
    case Tarjeta       = 'tarjeta';

    public function label(): string
    {
        return match ($this) {
            self::Efectivo      => 'Efectivo',
            self::Transferencia => 'Transferencia',
            self::Tarjeta       => 'Tarjeta',
        };
    }

    // True si el medio afecta la gaveta física: solo el efectivo.
    public function affectsCashDrawer(): bool
    {
        return $this === self::Efectivo;
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
