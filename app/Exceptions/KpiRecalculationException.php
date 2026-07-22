<?php
// ERR-12B, HTTP 500 / normalmente resuelta en el job
namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;


class KpiRecalculationException extends Exception {
    public function __construct(string $msg = 'Snapshot de KPI inconsistente: se descarta y recalcula desde las tablas fuente.') { parent::__construct($msg); }
    public function render(): JsonResponse { return response()->json(['message'=>$this->getMessage(),'error'=>'KPI_RECALCULATION'], 500); }
}
