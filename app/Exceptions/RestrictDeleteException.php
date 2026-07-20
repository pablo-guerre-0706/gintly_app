<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;

class RestrictDeleteException extends Exception
{
    public function __construct(
        string $message = 'No se puede eliminar: el registro tiene dependencias. Use la desactivación lógica.'
    ) {
        parent::__construct($message);
    }

    public function render(): JsonResponse
    {
        return response()->json(['message' => $this->getMessage(), 'error' => 'RESTRICT_DELETE'], 409);
    }
}
