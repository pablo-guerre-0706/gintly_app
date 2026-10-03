<?php

declare(strict_types=1);

namespace App\Enums;

enum ReturnReasonCode: string
{
    case Vencido        = 'vencido';
    case DefectoFabrica = 'defecto_fabrica';
    case ErrorDespacho  = 'error_despacho';
    case Insatisfaccion = 'insatisfaccion';
    case Otro           = 'otro';

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_map(static fn (self $s): string => $s->value, self::cases());
    }

    /**
     * Regla de dominio (RF-10-01): 'vencido' y 'defecto_fabrica' NUNCA pueden reingresar
     * al stock vendible. El motor no lo restringe (Fase 1); lo valida enum + request + servicio.
     */
    public function allowsReentry(): bool
    {
        return ! in_array($this, [self::Vencido, self::DefectoFabrica], true);
    }

    /** Destino sugerido por el motivo (RF-10-01). */
    public function suggestedDestination(): ReturnDestination
    {
        return $this->allowsReentry() ? ReturnDestination::Reingreso : ReturnDestination::Merma;
    }

    public function label(): string
    {
        return match ($this) {
            self::Vencido        => 'Vencido',
            self::DefectoFabrica => 'Defecto de fábrica',
            self::ErrorDespacho  => 'Error de despacho',
            self::Insatisfaccion => 'Insatisfacción',
            self::Otro           => 'Otro',
        };
    }
}
