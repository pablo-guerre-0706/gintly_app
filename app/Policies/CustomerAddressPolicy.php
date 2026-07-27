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
            return Response::deny('El cliente indicado no pertenece a su negocio.');
        }

        if ($customer->isProtected()) {
            return Response::deny('El "Consumidor Final" no admite direcciones.');
        }

        return $this->hasAtLeast($actor, RoleName::Operator)
            ? Response::allow()
            : Response::deny('No tiene autorización para registrar direcciones.');
    }

    public function update(User $actor, CustomerAddress $address): Response
    {
        if (! $this->sharesBusinessWith($actor, $address)) {
            return Response::deny('La dirección indicada no pertenece a su negocio.');
        }

        return $this->hasAtLeast($actor, RoleName::Operator)
            ? Response::allow()
            : Response::deny('No tiene autorización para modificar direcciones.');
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

