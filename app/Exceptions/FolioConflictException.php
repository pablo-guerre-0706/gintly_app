<?php
// (ERR-07, HTTP 409)
namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;


class FolioConflictException extends Exception {
    public function __construct(string $msg = 'Conflicto de folio: reintente la operación.') { parent::__construct($msg); }
    public function render(): JsonResponse { return response()->json(['message'=>$this->getMessage(),'error'=>'FOLIO_CONFLICT'], 409); }
}
