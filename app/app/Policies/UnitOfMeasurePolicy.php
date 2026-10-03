<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\RoleName;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Policies\Concerns\InteractsWithTenant;
use Illuminate\Auth\Access\Response;

final class UnitOfMeasurePolicy
{
    use InteractsWithTenant;

    public function viewAny(User $actor): Response
    {
        return $this->hasAtLeast($actor, RoleName::Admin)
            ? Response::allow()
            : Response::deny('No tiene autorización para consultar las unidades de medida.');
    }

    public function view(User $actor, UnitOfMeasure $unit): Response
    {
        if (! $this->sharesBusinessWith($actor, $unit)) {
            return Response::deny('La unidad solicitada no pertenece a su negocio.');
        }

        return $this->hasAtLeast($actor, RoleName::Admin)
            ? Response::allow()
            : Response::deny('No tiene autorización para consultar esta unidad.');
    }

    public function create(User $actor): Response
    {
        return $this->hasAtLeast($actor, RoleName::Admin)
            ? Response::allow()
            : Response::deny('No tiene autorización para registrar unidades de medida.');
    }

    public function update(User $actor, UnitOfMeasure $unit): Response
    {
        return $this->manage($actor, $unit);
    }

    public function delete(User $actor, UnitOfMeasure $unit): Response
    {
        return $this->manage($actor, $unit);
    }

    private function manage(User $actor, UnitOfMeasure $unit): Response
    {
        if (! $this->sharesBusinessWith($actor, $unit)) {
            return Response::deny('La unidad indicada no pertenece a su negocio.');
        }

        return $this->hasAtLeast($actor, RoleName::Admin)
            ? Response::allow()
            : Response::deny('No tiene autorización para gestionar unidades de medida.');
    }
}
