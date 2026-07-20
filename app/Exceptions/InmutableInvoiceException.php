<?php
// (ERR-07B, HTTP 403)
namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;


class ImmutableInvoiceException extends Exception {
    public function __construct(string $msg = 'El núcleo fiscal de una factura emitida es inmutable.') { parent::__construct($msg); }
    public function render(): JsonResponse { return response()->json(['message'=>$this->getMessage(),'error'=>'IMMUTABLE_INVOICE'], 403); }
}
