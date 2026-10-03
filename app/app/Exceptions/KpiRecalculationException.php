<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;
use Throwable;

final class KpiRecalculationException extends RuntimeException
{
    public function __construct(public readonly string $periodType, ?Throwable $previous = null)
    {
        parent::__construct('No se pudo recalcular el indicador de forma consistente; la instantánea se descarta.', 0, $previous);
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'code'        => 'KPI_RECALCULATION_FAILED',
            'message'     => $this->getMessage(),
            'period_type' => $this->periodType,
        ], 500);
    }
}
