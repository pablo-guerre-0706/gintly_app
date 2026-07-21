<?php

namespace App\Enums;

enum AnomalySeverity: string {
    case Informativa = 'informativa';  case Advertencia = 'advertencia';  case Critica = 'critica';
    /** La severidad decide el canal/urgencia (RF-11-06). */
    public function notifiesOwner(): bool { return $this === self::Critica; }
}
