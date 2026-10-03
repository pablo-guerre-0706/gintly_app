<?php

declare(strict_types=1);

namespace App\Enums;

enum CreditNoteStatus: string
{
    case Emitida = 'emitida';
    case Anulada = 'anulada';

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_map(static fn (self $s): string => $s->value, self::cases());
    }

    public function label(): string
    {
        return match ($this) {
            self::Emitida => 'Emitida',
            self::Anulada => 'Anulada',
        };
    }
}
