<?php

namespace App\Enums;

enum BusinessStatus: string
{
    case Active    = 'active';     // Operativo normal
    case Suspended = 'suspended';  // Bloqueado (impago, sanción) → no opera
    case Trial     = 'trial';      // Periodo de prueba → sí opera

    /** Regla de dominio: qué estados permiten operar el negocio. */
    public function canOperate(): bool
    {
        return match ($this) {
            self::Active, self::Trial => true,
            self::Suspended           => false,
        };
    }
}