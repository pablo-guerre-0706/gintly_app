<?php

namespace App\Enums;

enum AnomalyStatus: string {
    case Detectada  = 'detectada';   case Notificada = 'notificada';   case EnRevision = 'en_revision';
    case Justificada = 'justificada'; case Resuelta   = 'resuelta';
    /** Estados que mantienen el candado de idempotencia activo (RF-11 ERR-11B). */
    public function isActive(): bool {
        return in_array($this, [self::Detectada, self::Notificada, self::EnRevision], true);
    }
}
