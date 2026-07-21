<?php
// ERR-10B, HTTP 422
namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;


class InvalidRefundMethodException extends Exception {
    public function __construct(string $msg = 'No se puede reembolsar en efectivo un monto que el cliente aún no ha pagado.') { parent::__construct($msg); }
    public function render(): JsonResponse { return response()->json(['message'=>$this->getMessage(),'error'=>'INVALID_REFUND_METHOD'], 422); }
}
