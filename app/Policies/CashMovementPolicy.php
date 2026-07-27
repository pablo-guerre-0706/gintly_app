<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\RoleName;
use App\Models\CashMovement;
use App\Models\User;
use App\Policies\Concerns\InteractsWithTenant;
use Illuminate\Auth\Access\Response;


final class CashMovementPolicy
{
    use InteractsWithTenant;

    public function viewAny(User $actor): Response
    {
        return $this->hasAtLeast($actor, RoleName::Admin)
            ? Response::allow()
            : Response::deny('No tiene autorización para consultar los movimientos de caja.');
    }

    public function view(User $actor, CashMovement $movement): Response
    {
        if (! $this->sharesBusinessWith($actor, $movement)) {
            return Response::deny('El movimiento solicitado no pertenece a su negocio.');
        }

        return $this->hasAtLeast($actor, RoleName::Operator)
            ? Response::allow()
            : Response::deny('No tiene autorización para consultar este movimiento.');
    }

    // El cajero registra movimientos manuales (retiros, egresos, ajustes).
    public function create(User $actor): Response
    {
        return $this->hasAtLeast($actor, RoleName::Operator)
            ? Response::allow()
            : Response::deny('No tiene autorización para registrar movimientos de caja.');
    }

    public function update(User $actor, CashMovement $movement): Response
    {
        return Response::deny('Los movimientos de caja son inmutables: no admiten modificación.');
    }

    public function delete(User $actor, CashMovement $movement): Response
    {
        return Response::deny('Los movimientos de caja son inmutables: no admiten eliminación.');
    }
}

