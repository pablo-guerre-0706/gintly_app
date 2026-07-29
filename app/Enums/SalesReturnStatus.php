<?php

declare(strict_types=1);

namespace App\Enums;

enum SalesReturnStatus: string
{
    case Registrada = 'registrada';
    case Procesada  = 'procesada';
    case Anulada    = 'anulada';

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_map(static fn (self $s): string => $s->value, self::cases());
    }

    public function isProcessed(): bool
    {
        return $this === self::Procesada;
    }

    public function label(): string
    {
        return match ($this) {
            self::Registrada => 'Registrada',
            self::Procesada  => 'Procesada',
            self::Anulada    => 'Anulada',
        };
    }
}
