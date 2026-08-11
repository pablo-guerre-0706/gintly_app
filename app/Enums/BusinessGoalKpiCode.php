<?php

declare(strict_types=1);

namespace App\Enums;

enum BusinessGoalKpiCode: string
{
    case Kpi03              = 'kpi_03';
    case Kpi04              = 'kpi_04';
    case Kpi05              = 'kpi_05';
    case Kpi08              = 'kpi_08';
    case Margen            = 'margen';
    case TicketPromedio    = 'ticket_promedio';
    case RotacionInventario = 'rotacion_inventario';

    /** Códigos con meta admitida (goalable). @return array<int, string> */
    public static function values(): array
    {
        return array_map(static fn (self $c): string => $c->value, self::cases());
    }

    public function label(): string
    {
        return (string) (config("kpis.{$this->value}.label") ?? $this->value);
    }
}