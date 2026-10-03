<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\RoleName;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\User;
use App\Policies\Concerns\InteractsWithTenant;
use Illuminate\Auth\Access\Response;


// Las direcciones se gobiernan por el cliente titular.
final class CustomerAddressPolicy
{
    use InteractsWithTenant;

    public function viewAny(User $actor, Customer $customer): Response
    {
        if (! $this->sharesBusinessWith($actor, $customer)) {
            return Response::deny('El cliente indicado no pertenece a su negocio.');
        }

        return $this->hasAtLeast($actor, RoleName::Operator)
            ? Response::allow()
            : Response::deny('No tiene autorización para consultar las direcciones.');
    }

    public function create(User $actor, Customer $customer): Response
    {
        if (! $this->sharesBusinessWith($actor, $customer)) {
            return Response::denyWithStatus(404, 'El cliente indicado no pertenece a su negocio.');
        }

        return $this->guardByTitular($actor, $customer);
    }

    public function update(User $actor, CustomerAddress $address): Response
    {
        if (! $this->sharesBusinessWith($actor, $address)) {
            return Response::denyWithStatus(404, 'La dirección indicada no pertenece a su negocio.');
        }

        return $this->guardByTitular($actor, $address->customer);
    }

    /**
     * Titular normal    -> Operator+ puede crear/actualizar.
     * Titular protegido -> sólo Admin+ (si el negocio lo requiere).
     */
    private function guardByTitular(User $actor, ?Customer $customer): Response
    {
        if ($customer?->isProtected()) {
            return $this->hasAtLeast($actor, RoleName::Admin)
                ? Response::allow()
                : Response::denyWithStatus(403, 'El «Consumidor Final» no admite direcciones gestionadas por el rol operativo.');
        }

        return $this->hasAtLeast($actor, RoleName::Operator)
            ? Response::allow()
            : Response::denyWithStatus(403, 'No tiene autorización para gestionar direcciones.');
    }

    public function delete(User $actor, CustomerAddress $address): Response
    {
        if (! $this->sharesBusinessWith($actor, $address)) {
            return Response::deny('La dirección indicada no pertenece a su negocio.');
        }

        return $this->hasAtLeast($actor, RoleName::Admin)
            ? Response::allow()
            : Response::deny('No tiene autorización para eliminar direcciones.');
    }
}

