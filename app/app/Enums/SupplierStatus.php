<?php

declare(strict_types=1);

namespace App\Enums;


// Ciclo de aprobación del proveedor (suppliers.status · RF-04-02).
enum SupplierStatus: string
{
    case Pendiente  = 'pendiente';
    case Aprobado   = 'aprobado';
    case Suspendido = 'suspendido';

    public function label(): string
    {
        return match ($this) {
            self::Pendiente  => 'Pendiente de aprobación',
            self::Aprobado   => 'Aprobado',
            self::Suspendido => 'Suspendido',
        };
    }

    // Solo un proveedor aprobado puede recibir órdenes de compra (ERR-04B).
    public function canReceiveOrders(): bool
    {
        return $this === self::Aprobado;
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
