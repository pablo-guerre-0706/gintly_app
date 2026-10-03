<?php

declare(strict_types=1);

namespace App\Enums;


// Ciclo de vida de un conteo físico (physical_counts.status).
enum PhysicalCountStatus: string
{
    case Abierto     = 'abierto';
    case Justificado = 'justificado';
    case Ajustado    = 'ajustado';

    public function label(): string
    {
        return match ($this) {
            self::Abierto     => 'Abierto',
            self::Justificado => 'Justificado',
            self::Ajustado    => 'Ajustado',
        };
    }

    public function isOpen(): bool
    {
        return $this === self::Abierto;
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
