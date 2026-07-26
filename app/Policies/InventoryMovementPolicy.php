<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\RoleName;
use App\Models\InventoryMovement;
use App\Models\User;
use App\Policies\Concerns\InteractsWithTenant;
use Illuminate\Auth\Access\Response;

// Kardex de solo lectura
final class InventoryMovementPolicy
{
    use InteractsWithTenant;

    public function viewAny(User $actor): Response
    {
        return $this->hasAtLeast($actor, RoleName::Admin)
            ? Response::allow()
            : Response::deny('No tiene autorización para consultar el kardex.');
    }

    public function view(User $actor, InventoryMovement $movement): Response
    {
        if (! $this->sharesBusinessWith($actor, $movement)) {
            return Response::deny('El movimiento solicitado no pertenece a su negocio.');
        }

        return $this->hasAtLeast($actor, RoleName::Admin)
            ? Response::allow()
            : Response::deny('No tiene autorización para consultar este movimiento.');
    }

    public function create(User $actor): Response
    {
        return Response::deny('El kardex solo lo escribe el sistema mediante operaciones de inventario.');
    }

    public function update(User $actor, InventoryMovement $movement): Response
    {
        return Response::deny('El kardex es inmutable: no admite modificación.');
    }

    public function delete(User $actor, InventoryMovement $movement): Response
    {
        return Response::deny('El kardex es inmutable: no admite eliminación.');
    }
}

