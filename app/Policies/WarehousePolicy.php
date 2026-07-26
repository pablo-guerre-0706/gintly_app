<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\RoleName;
use App\Models\User;
use App\Models\Warehouse;
use App\Policies\Concerns\InteractsWithTenant;
use Illuminate\Auth\Access\Response;

final class WarehousePolicy
{
    use InteractsWithTenant;

    public function viewAny(User $actor): Response
    {
        return $this->hasAtLeast($actor, RoleName::Operator)
            ? Response::allow()
            : Response::deny('No tiene autorización para consultar las bodegas.');
    }

    public function view(User $actor, Warehouse $warehouse): Response
    {
        if (! $this->sharesBusinessWith($actor, $warehouse)) {
            return Response::deny('La bodega solicitada no pertenece a su negocio.');
        }

        return $this->hasAtLeast($actor, RoleName::Admin)
            ? Response::allow()
            : Response::deny('No tiene autorización para consultar esta bodega.');
    }

    public function create(User $actor): Response
    {
        return $this->hasAtLeast($actor, RoleName::Admin)
            ? Response::allow()
            : Response::deny('No tiene autorización para registrar bodegas.');
    }

    public function update(User $actor, Warehouse $warehouse): Response
    {
        return $this->manage($actor, $warehouse);
    }

    public function delete(User $actor, Warehouse $warehouse): Response
    {
        return $this->manage($actor, $warehouse);
    }

    private function manage(User $actor, Warehouse $warehouse): Response
    {
        if (! $this->sharesBusinessWith($actor, $warehouse)) {
            return Response::deny('La bodega indicada no pertenece a su negocio.');
        }

        return $this->hasAtLeast($actor, RoleName::Admin)
            ? Response::allow()
            : Response::deny('No tiene autorización para gestionar bodegas.');
    }
}
