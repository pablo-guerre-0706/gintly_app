<?php

declare(strict_types=1);

namespace App\Enums;

enum ReconciliationRunType: string
{
    case Programada = 'programada';
    case Manual     = 'manual';

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_map(static fn (self $t): string => $t->value, self::cases());
    }

    public function label(): string
    {
        return match ($this) {
            self::Programada => 'Programada',
            self::Manual     => 'Manual',
        };
    }
}