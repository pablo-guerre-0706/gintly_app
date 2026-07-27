<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Estado de la sesión de caja (cash_sessions.status).
 */
enum CashSessionStatus: string
{
    case Abierta     = 'abierta';
    case Cerrada     = 'cerrada';
    case Descuadrada = 'descuadrada';

    public function label(): string
    {
        return match ($this) {
            self::Abierta     => 'Abierta',
            self::Cerrada     => 'Cerrada',
            self::Descuadrada => 'Descuadrada',
        };
    }

    public function isOpen(): bool
    {
        return $this === self::Abierta;
    }

    // Estados de cierre: el esperado y la diferencia YA son visibles
    public function isClosed(): bool
    {
        return $this !== self::Abierta;
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
