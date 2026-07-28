<?php

declare(strict_types=1);

namespace App\Enums;

enum DeliveryState: string
{
    case Pendiente  = 'pendiente';   // Nada retirado.
    case Parcial    = 'parcial';     // Retirado en parte.
    case Completado = 'completado';  // Todo retirado.

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_map(static fn (self $s): string => $s->value, self::cases());
    }

    /**
     * Deriva el estado de entrega de una factura (RF-09-02) a partir de banderas agregadas
     * sobre las líneas ENTREGABLES (excluye servicios).
     */
    public static function derive(bool $anyDispatched, bool $allDispatched): self
    {
        if ($allDispatched) {
            return self::Completado;
        }

        return $anyDispatched ? self::Parcial : self::Pendiente;
    }

    public function label(): string
    {
        return match ($this) {
            self::Pendiente  => 'Pendiente',
            self::Parcial    => 'Parcial',
            self::Completado => 'Completado',
        };
    }
}
