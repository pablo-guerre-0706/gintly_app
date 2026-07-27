<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\RoleName;
use App\Models\CashSession;
use App\Models\User;
use App\Policies\Concerns\InteractsWithTenant;
use Illuminate\Auth\Access\Response;

final class CashSessionPolicy
{
    use InteractsWithTenant;

    public function viewAny(User $actor): Response
    {
        return $this->hasAtLeast($actor, RoleName::Operator)
            ? Response::allow()
            : Response::deny('No tiene autorización para consultar las sesiones de caja.');
    }

    public function view(User $actor, CashSession $session): Response
    {
        if (! $this->sharesBusinessWith($actor, $session)) {
            return Response::deny('La sesión solicitada no pertenece a su negocio.');
        }

        return $this->hasAtLeast($actor, RoleName::Operator)
            ? Response::allow()
            : Response::deny('No tiene autorización para consultar esta sesión.');
    }

    // El cajero (ROL-03) abre su propia sesión (RF-06-03).
    public function open(User $actor): Response
    {
        return $this->hasAtLeast($actor, RoleName::Operator)
            ? Response::allow()
            : Response::deny('No tiene autorización para abrir sesiones de caja.');
    }

    // El cierre lo ejecuta el cajero que arquea. El servicio verifica que la sesión esté abierta.
    public function close(User $actor, CashSession $session): Response
    {
        if (! $this->sharesBusinessWith($actor, $session)) {
            return Response::deny('La sesión indicada no pertenece a su negocio.');
        }

        return $this->hasAtLeast($actor, RoleName::Operator)
            ? Response::allow()
            : Response::deny('No tiene autorización para cerrar sesiones de caja.');
    }
}
