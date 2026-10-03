<?php

declare(strict_types=1);

namespace App\Enums;

enum DispatchStatus: string
{
    case Registrado = 'registrado';
    case Revertido  = 'revertido';

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_map(static fn (self $s): string => $s->value, self::cases());
    }

    /** ¿El retiro está vigente (no revertido)? */
    public function isActive(): bool
    {
        return $this === self::Registrado;
    }

    /** Solo un retiro 'registrado' puede revertirse (RF-09-04). */
    public function canRevert(): bool
    {
        return $this === self::Registrado;
    }

    public function label(): string
    {
        return match ($this) {
            self::Registrado => 'Registrado',
            self::Revertido  => 'Revertido',
        };
    }
}
