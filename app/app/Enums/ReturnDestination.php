<?php

declare(strict_types=1);

namespace App\Enums;

enum ReturnDestination: string
{
    case Reingreso = 'reingreso'; // Vuelve al stock vendible, a costo.
    case Merma     = 'merma';     // Pérdida: no vuelve al stock.

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_map(static fn (self $s): string => $s->value, self::cases());
    }

    public function isReentry(): bool
    {
        return $this === self::Reingreso;
    }

    public function isMerma(): bool
    {
        return $this === self::Merma;
    }

    public function label(): string
    {
        return match ($this) {
            self::Reingreso => 'Reingreso',
            self::Merma     => 'Merma',
        };
    }
}
