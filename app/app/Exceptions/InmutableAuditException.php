<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;

class InmutableAuditException extends Exception
{
    public function __construct(
        string $message = 'Los registros de auditoría son inmutables (solo INSERT).'
    ) {
        parent::__construct($message);
    }

    // Laravel invoca render() automáticamente → responde 403 sin tocar el Handler.
    public function render(): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'error'   => 'INMUTABLE_AUDIT',
        ], 403);
    }
}