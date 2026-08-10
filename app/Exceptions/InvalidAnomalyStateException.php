<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

final class InvalidAnomalyStateException extends RuntimeException
{
    private function __construct(string $message)
    {
        parent::__construct($message);
    }

    public static function notJustifiable(): self
    {
        return new self('La anomalía no está en un estado justificable.');
    }

    public static function notResolvable(): self
    {
        return new self('La anomalía ya está resuelta.');
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'code'    => 'INVALID_ANOMALY_STATE',
            'message' => $this->getMessage(),
        ], 422);
    }
}
