<?php

declare(strict_types=1);

namespace App\Enums;

enum ReconciliationStatus: string
{
    case EnProceso  = 'en_proceso';
    case Completada = 'completada';
    case Fallida    = 'fallida';

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_map(static fn (self $s): string => $s->value, self::cases());
    }

    public function label(): string
    {
        return match ($this) {
            self::EnProceso  => 'En proceso',
            self::Completada => 'Completada',
            self::Fallida    => 'Fallida',
        };
    }
}
