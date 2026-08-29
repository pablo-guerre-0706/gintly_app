<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\RoleName;
use App\Models\Customer;
use App\Models\User;
use App\Policies\Concerns\InteractsWithTenant;
use Illuminate\Auth\Access\Response;


final class CustomerPolicy
{
    use InteractsWithTenant;

    public function viewAny(User $actor): Response
    {
        return $this->hasAtLeast($actor, RoleName::Operator)
            ? Response::allow()
            : Response::deny('No tiene autorización para consultar los clientes.');
    }

    public function view(User $actor, Customer $customer): Response
    {
        if (! $this->sharesBusinessWith($actor, $customer)) {
            return Response::deny('El cliente solicitado no pertenece a su negocio.');
        }

        return $this->hasAtLeast($actor, RoleName::Operator)
            ? Response::allow()
            : Response::deny('No tiene autorización para consultar este cliente.');
    }

    // El operativo (ROL-03) registra clientes en el mostrador.
    public function create(User $actor): Response
    {
        return $this->hasAtLeast($actor, RoleName::Operator)
            ? Response::allow()
            : Response::deny('No tiene autorización para registrar clientes.');
    }

    // Editar es ROL-02, y NUNCA el Consumidor Final (PROTECTED_RESOURCE).
    public function update(User $actor, Customer $customer): Response
    {
        return $this->guardMutation($actor, $customer, 'modificar');
    }

    public function delete(User $actor, Customer $customer): Response
    {
        return $this->guardMutation($actor, $customer, 'dar de baja');
    }

    /**
     * Validación unificada de mutaciones (Seguridad + Recurso Protegido + Rol)
     */
    private function guardMutation(User $actor, Customer $customer, string $accion): Response
    {
        if (! $this->sharesBusinessWith($actor, $customer)) {
            return Response::denyWithStatus(404, 'El cliente indicado no pertenece a su negocio.');
        }

        // Candado del recurso protegido ANTES del rango (si se invirtiera, un Admin
        // pasaría el rango y podría mutar el «Consumidor Final»).
        if ($customer->isProtected()) {
            return Response::denyWithStatus(
                403,
                "El «Consumidor Final» es un cliente protegido del sistema y no puede {$accion}se.",
            );
        }

        return $this->hasAtLeast($actor, RoleName::Admin)
            ? Response::allow()
            : Response::denyWithStatus(403, "No tiene autorización para {$accion} clientes.");
    }

    // --- MOD-08: autorización de los endpoints de crédito del cliente ---
    // GET /customers/{id}/credit-status — estado de crédito consolidado
    public function viewCreditStatus(User $user, Customer $customer): bool
    {
        return $this->sharesBusinessWith($user, $customer)
            && $this->hasAtLeast($user, RoleName::Admin);
    }

    // POST /customers/{id}/credit-check — evaluación preventiva de cupo (ROL-03+, RF-08-02).
    public function checkCredit(User $user, Customer $customer): bool
    {
        return $this->sharesBusinessWith($user, $customer)
            && $this->hasAtLeast($user, RoleName::Operator);
    }

    // --- MOD-10: saldo a favor del cliente (ROL-02) ---
    public function viewCreditBalance(User $user, Customer $customer): bool
    {
        return $this->sharesBusinessWith($user, $customer)
            && $this->hasAtLeast($user, RoleName::Admin);
    }
}
