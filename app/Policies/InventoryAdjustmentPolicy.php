<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\RoleName;
use App\Models\InventoryAdjustment;
use App\Models\User;
use App\Policies\Concerns\InteractsWithTenant;
use Illuminate\Auth\Access\Response;

final class InventoryAdjustmentPolicy
{
    use InteractsWithTenant;

    public function viewAny(User $actor): Response
    {
        return $this->hasAtLeast($actor, RoleName::Admin)
            ? Response::allow()
            : Response::deny('No tiene autorización para consultar los ajustes de inventario.');
    }

    public function view(User $actor, InventoryAdjustment $adjustment): Response
    {
        if (! $this->sharesBusinessWith($actor, $adjustment)) {
            return Response::deny('El ajuste solicitado no pertenece a su negocio.');
        }

        return $this->hasAtLeast($actor, RoleName::Admin)
            ? Response::allow()
            : Response::deny('No tiene autorización para consultar este ajuste.');
    }

    // El ajuste directo (merma/sobrante/corrección) es potestad de ROL-02:
    public function create(User $actor): Response
    {
        return $this->hasAtLeast($actor, RoleName::Admin)
            ? Response::allow()
            : Response::deny('No tiene autorización para registrar ajustes de inventario.');
    }
}
