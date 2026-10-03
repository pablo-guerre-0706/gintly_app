<?php

namespace App\Exceptions;

use Exception;

class DuplicateAnomalyException extends Exception
{
    // Interna: se lanza al chocar contra uniq_active_anomaly y el servicio la silencia (idempotencia).
    public function __construct(string $message = 'Anomalía activa duplicada (deduplicada).')
    {
        parent::__construct($message);
    }
}
