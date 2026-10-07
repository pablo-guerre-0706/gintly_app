<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * Errores de GESTIÓN de la misma suscripción (cancelación / cambio de plan) que no son indisponibilidad del
 * proveedor ni un límite superado. Self-render con código estable.
 *   - noSubscription()     · 409 NO_ACTIVE_SUBSCRIPTION  (no hay una suscripción vigente que gestionar).
 *   - notProvisioned()     · 409 NO_ACTIVE_SUBSCRIPTION  (sin id de suscripción del proveedor todavía).
 *   - sameSelection()      · 422 PLAN_CHANGE_INVALID     (el destino es idéntico al plan/periodicidad actuales).
 */
final class SubscriptionManagementException extends RuntimeException
{
    public function __construct(string $message, private readonly string $errorCode, private readonly int $status)
    {
        parent::__construct($message);
    }

    public static function noSubscription(): self
    {
        return new self('No hay una suscripción vigente que gestionar.', 'NO_ACTIVE_SUBSCRIPTION', 409);
    }

    public static function notProvisioned(): self
    {
        return new self('La suscripción aún no está confirmada por el proveedor; intente más tarde.', 'NO_ACTIVE_SUBSCRIPTION', 409);
    }

    public static function sameSelection(): self
    {
        return new self('El plan y la periodicidad indicados coinciden con los vigentes.', 'PLAN_CHANGE_INVALID', 422);
    }

    public function render(): JsonResponse
    {
        return response()->json(['message' => $this->getMessage(), 'code' => $this->errorCode], $this->status);
    }
}
