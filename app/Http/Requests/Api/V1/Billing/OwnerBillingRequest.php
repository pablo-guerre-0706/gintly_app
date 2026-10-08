<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Billing;

use App\Enums\RoleName;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Base de las acciones de gestión comercial (contratación, cambio de plan, cancelación): exige sesión Sanctum
 * stateful + CSRF (por la configuración del proyecto) y PROPIETARIO REAL activo con ROL-01 (owner_user_id
 * coherente; no basta una indicación del cliente). El negocio procede siempre de la sesión.
 */
abstract class OwnerBillingRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if (! $user instanceof User || ! $user->is_active) {
            return false;
        }

        $business = $user->business;

        return $business !== null
            && (int) $business->owner_user_id === (int) $user->getKey()
            && $user->holdsAtLeast(RoleName::Owner);
    }
}
