<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\RoleName;
use App\Models\CashRegister;
use App\Models\User;
use App\Policies\Concerns\InteractsWithTenant;
use Illuminate\Auth\Access\Response;

final class CashRegisterPolicy
{
    use InteractsWithTenant;

    public function viewAny(User $actor): Response
    {
        return $this->hasAtLeast($actor, RoleName::Operator)
            ? Response::allow()
            : Response::deny('No tiene autorización para consultar las cajas.');
    }

    public function view(User $actor, CashRegister $register): Response
    {
        if (! $this->sharesBusinessWith($actor, $register)) {
            return Response::deny('La caja solicitada no pertenece a su negocio.');
        }

        return $this->hasAtLeast($actor, RoleName::Operator)
            ? Response::allow()
            : Response::deny('No tiene autorización para consultar esta caja.');
    }

    public function create(User $actor): Response
    {
        return $this->hasAtLeast($actor, RoleName::Admin)
            ? Response::allow()
            : Response::deny('No tiene autorización para registrar cajas.');
    }

    public function update(User $actor, CashRegister $register): Response
    {
        return $this->manage($actor, $register);
    }

    public function delete(User $actor, CashRegister $register): Response
    {
        return $this->manage($actor, $register);
    }

    private function manage(User $actor, CashRegister $register): Response
    {
        if (! $this->sharesBusinessWith($actor, $register)) {
            return Response::deny('La caja indicada no pertenece a su negocio.');
        }

        return $this->hasAtLeast($actor, RoleName::Admin)
            ? Response::allow()
            : Response::deny('No tiene autorización para gestionar cajas.');
    }
}
