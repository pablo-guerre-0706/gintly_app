<?php
// (ERR-07, HTTP 422)
namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;


class IncompletePaymentException extends Exception {
    public function __construct(string $msg = 'El pago no cubre el 100% del total al contado.') { parent::__construct($msg); }
    public function render(): JsonResponse { return response()->json(['message'=>$this->getMessage(),'error'=>'INCOMPLETE_PAYMENT'], 422); }
}
