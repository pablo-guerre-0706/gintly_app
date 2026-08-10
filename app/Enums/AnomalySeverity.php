<?php

declare(strict_types=1);

namespace App\Enums;

enum AnomalySeverity: string
{
    case Informativa = 'informativa';
    case Advertencia = 'advertencia';
    case Critica     = 'critica';

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_map(static fn (self $s): string => $s->value, self::cases());
    }

    public function label(): string
    {
        return match ($this) {
            self::Informativa => 'Informativa',
            self::Advertencia => 'Advertencia',
            self::Critica     => 'Crítica',
        };
    }
}
