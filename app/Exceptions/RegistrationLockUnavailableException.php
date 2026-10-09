<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Support\RegistrationFailureReporter;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * HTTP 500 SANITIZADO · No se pudo adquirir el lock de serialización por Idempotency-Key (timeout o error de
 * infraestructura del motor). NO es un conflicto de slug ni de idempotencia: es indisponibilidad temporal. El
 * cliente debe reintentar con la MISMA Idempotency-Key (operación idempotente). El render no expone SQL, el
 * nombre del lock, fingerprints ni trazas.
 */
final class RegistrationLockUnavailableException extends RuntimeException
{
    public readonly string $diagnosticId;

    public function __construct(
        string $message = 'No se pudo completar el registro por indisponibilidad temporal. Reintente con la misma Idempotency-Key.',
        public readonly string $reason = 'unspecified',
        public readonly int $waitSeconds = 10,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
        $this->diagnosticId = (string) Str::uuid();
    }

    /** Laravel stops its default raw-exception report when this method returns normally. */
    public function report(RegistrationFailureReporter $reporter): void
    {
        $reporter->record($this, 'acquire_lock', $this->diagnosticId, $this->reason, $this->waitSeconds);
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
        ], 500, ['X-Registration-Diagnostic-ID' => $this->diagnosticId]);
    }
}
