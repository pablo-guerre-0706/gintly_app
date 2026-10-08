<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Billing;

/**
 * Cancelación de las renovaciones de la MISMA suscripción. Sin cuerpo: el negocio procede de la sesión y la
 * autorización (propietario real ROL-01) la aporta la base. No recibe claves del cliente.
 */
final class CancelSubscriptionRequest extends OwnerBillingRequest
{
    public function rules(): array
    {
        return [];
    }
}
