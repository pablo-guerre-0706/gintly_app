<?php

declare(strict_types=1);

namespace App\Enums;


// Veredicto del 3-Way Match sobre una recepción (goods_receipts.match_status).
enum GoodsReceiptMatchStatus: string
{
    case Ok           = 'ok';
    case Discrepancia = 'discrepancia';
    case Bloqueada    = 'bloqueada';

    public function label(): string
    {
        return match ($this) {
            self::Ok           => 'Conforme',
            self::Discrepancia => 'Con discrepancia',
            self::Bloqueada    => 'Bloqueada',
        };
    }

    // Solo una recepción conforme habilita el ingreso a inventario.
    public function allowsInventoryEntry(): bool
    {
        return $this === self::Ok;
    }

    // Solo una recepción en discrepancia admite resolución de ROL-01.
    public function isResolvable(): bool
    {
        return $this === self::Discrepancia;
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
