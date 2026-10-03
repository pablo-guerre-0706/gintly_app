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

    // Lectura ROL-03: exige perfil BODEGUERO con bodegas.ver. ROL-01/ROL-02 por nivel.
    public function viewAny(User $actor): Response
    {
        return $this->hasAtLeast($actor, RoleName::Operator) && $this->operatorGrants($actor, 'bodegas.ver')
            ? Response::allow()
            : Response::deny('No tiene autorización para consultar las bodegas.');
    }

    public function view(User $actor, Warehouse $warehouse): Response
    {
        if (! $this->sharesBusinessWith($actor, $warehouse)) {
            return Response::deny('La bodega solicitada no pertenece a su negocio.');
        }

        // ROL-01/ROL-02: alcance de negocio. ROL-03 (bodeguero, bodegas.ver): solo su sucursal.
        if (! $this->hasAtLeast($actor, RoleName::Operator) || ! $this->operatorGrants($actor, 'bodegas.ver')) {
            return Response::deny('No tiene autorización para consultar esta bodega.');
        }

        return $this->operatorInBranch($actor, $warehouse->branch_id)
            ? Response::allow()
            : Response::deny('La bodega pertenece a otra sucursal.');
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
