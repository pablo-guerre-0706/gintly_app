<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * MOD-06 · Monedas admitidas para el efectivo. Moneda BASE del negocio = NIO (tasa 1). USD se consolida
 * a NIO mediante un tipo de cambio con snapshot por operación. Fuente única de las monedas válidas.
 */
enum Currency: string
{
    case Nio = 'NIO';
    case Usd = 'USD';

    /** Moneda base del negocio: toda consolidación se expresa en ella. */
    public static function base(): self
    {
        return self::Nio;
    }

    public function isBase(): bool
    {
        return $this === self::base();
    }

    public function label(): string
    {
        return match ($this) {
            self::Nio => 'Córdoba (NIO)',
            self::Usd => 'Dólar (USD)',
        };
    }

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_map(static fn (self $c): string => $c->value, self::cases());
    }
}
