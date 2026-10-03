<?php

declare(strict_types=1);

namespace App\Enums;


// Ciclo de vida de la orden de compra (purchase_orders.status).
enum PurchaseOrderStatus: string
{
    case Borrador  = 'borrador';
    case Emitida   = 'emitida';
    case Parcial   = 'parcial';
    case Recibida  = 'recibida';
    case Cancelada = 'cancelada';

    public function label(): string
    {
        return match ($this) {
            self::Borrador  => 'Borrador',
            self::Emitida   => 'Emitida',
            self::Parcial   => 'Recibida parcialmente',
            self::Recibida  => 'Recibida',
            self::Cancelada => 'Cancelada',
        };
    }

    // Solo se recibe mercancía contra órdenes emitidas o parciales (RF-04-03).
    public function canReceive(): bool
    {
        return $this === self::Emitida || $this === self::Parcial;
    }

    // La orden solo se edita en borrador.
    public function isEditable(): bool
    {
        return $this === self::Borrador;
    }

    // borrador y emitida admiten cancelación; una orden ya recibida no.
    public function canCancel(): bool
    {
        return $this === self::Borrador || $this === self::Emitida;
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
