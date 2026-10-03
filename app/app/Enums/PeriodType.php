<?php

declare(strict_types=1);

namespace App\Enums;

enum PeriodType: string
{
    case Diario   = 'diario';
    case Semanal  = 'semanal';
    case Mensual  = 'mensual';
    case Anual    = 'anual';

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_map(static fn (self $p): string => $p->value, self::cases());
    }

    public function label(): string
    {
        return match ($this) {
            self::Diario  => 'Diario',
            self::Semanal => 'Semanal',
            self::Mensual => 'Mensual',
            self::Anual   => 'Anual',
        };
    }
}
