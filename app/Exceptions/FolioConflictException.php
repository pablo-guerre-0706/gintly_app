<?php
// (ERR-07, HTTP 409)
namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;

class FolioConflictException extends Exception 
{
    public function __construct(string $msg = 'Conflicto de folio: reintente la operación.') 
    { 
        parent::__construct($msg); 
    }

    /**
     * Factoría estática para permitir la sintaxis FolioConflictException::make()
     */
    public static function make(string $msg = 'Conflicto de folio: reintente la operación.'): self
    {
        return new self($msg);
    }

    public function render(): JsonResponse 
    { 
        return response()->json([
            'message' => $this->getMessage(),
            'error' => 'FOLIO_CONFLICT'
        ], 409); 
    }
}
