<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\RoleName;
use App\Models\StockLevel;
use App\Models\User;
use App\Policies\Concerns\InteractsWithTenant;
use Illuminate\Auth\Access\Response;

final class StockLevelPolicy
{
    use InteractsWithTenant;

    public function viewAny(User $actor): Response
    {
        return $this->hasAtLeast($actor, RoleName::Operator)
            ? Response::allow()
            : Response::deny('No tiene autorización para consultar el inventario.');
    }

    public function view(User $actor, StockLevel $stockLevel): Response
    {
        if (! $this->sharesBusinessWith($actor, $stockLevel)) {
            return Response::deny('El saldo solicitado no pertenece a su negocio.');
        }

        return $this->hasAtLeast($actor, RoleName::Operator)
            ? Response::allow()
            : Response::deny('No tiene autorización para consultar este saldo.');
    }

    // Solo edición de umbrales min/max (H-27). quantity/reserved no por API.
    public function updateThresholds(User $actor, StockLevel $stockLevel): Response
    {
        if (! $this->sharesBusinessWith($actor, $stockLevel)) {
            return Response::deny('El saldo indicado no pertenece a su negocio.');
        }

        return $this->hasAtLeast($actor, RoleName::Admin)
            ? Response::allow()
            : Response::deny('No tiene autorización para modificar los umbrales de inventario.');
    }
}
