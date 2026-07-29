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
        if (! $this->sharesBusinessWith($actor, $customer)) {
            return Response::deny('El cliente indicado no pertenece a su negocio.');
        }

        if ($customer->isProtected()) {
            return Response::deny('El "Consumidor Final" es un cliente protegido del sistema y no puede modificarse.');
        }

        return $this->hasAtLeast($actor, RoleName::Admin)
            ? Response::allow()
            : Response::deny('No tiene autorización para modificar clientes.');
    }

    public function delete(User $actor, Customer $customer): Response
    {
        if (! $this->sharesBusinessWith($actor, $customer)) {
            return Response::deny('El cliente indicado no pertenece a su negocio.');
        }

        if ($customer->isProtected()) {
            return Response::deny('El "Consumidor Final" es un cliente protegido del sistema y no puede eliminarse.');
        }

        return $this->hasAtLeast($actor, RoleName::Admin)
            ? Response::allow()
            : Response::deny('No tiene autorización para dar de baja clientes.');
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

    // --- MOD-10: saldo a favor del cliente (ROL-02+, RF-10-03) ---
    public function viewCreditBalance(User $user, Customer $customer): bool
    {
        return $this->sharesBusinessWith($user, $customer)
            && $this->hasAtLeast($user, RoleName::Admin);
    }
}
