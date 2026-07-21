<?php
// ERR-10, HTTP 422
namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;


class ReturnQuantityException extends Exception {
    public function __construct(private readonly string $returnable = '0.000', private readonly string $requested = '0.000') {
        parent::__construct('La cantidad a devolver excede lo efectivamente entregado de esa línea.');
    }
    public function render(): JsonResponse {
        return response()->json(['message'=>$this->getMessage(),'error'=>'RETURN_QUANTITY','returnable'=>$this->returnable,'requested'=>$this->requested], 422);
    }
}
