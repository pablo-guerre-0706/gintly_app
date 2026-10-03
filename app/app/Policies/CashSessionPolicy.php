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

    public function viewAny(User $user): bool
    {
        return $this->hasAtLeast($user, RoleName::Operator); // ROL-03
    }

    // Alcance por propiedad: ROL-01/ROL-02 ven cualquier sesión de su negocio
    // (visibilidad administrativa); ROL-03 SOLO la que él mismo abrió. Aplica a
    // show y a /movements (ambos autorizan 'view'). El arqueo ciego lo sigue
    // garantizando el Resource (oculta expected/difference mientras 'abierta').
    public function view(User $user, CashSession $session): bool
    {
        if (! $this->sharesBusinessWith($user, $session)) {
            return false;
        }

        if ($this->hasAtLeast($user, RoleName::Admin)) {
            return true;
        }

        return (int) $session->opened_by === (int) $user->id;
    }

    public function create(User $user): bool
    {
        // Fase 5: abrir sesión exige el rol operativo Y, para ROL-03, el perfil CAJERO.
        return $this->hasAtLeast($user, RoleName::Operator)
            && $this->operatorGrants($user, 'caja.abrir');
    }

    public function close(User $user, CashSession $session): Response
    {
        if (! $this->sharesBusinessWith($user, $session)) {
            return Response::denyWithStatus(404);
        }

        // Fase 5: un ROL-03 sin perfil cajero no cierra caja (aunque el rol contenga el permiso).
        if (! $this->operatorGrants($user, 'caja.cerrar')) {
            return Response::denyWithStatus(403, 'Requiere el perfil de cajero para cerrar caja.');
        }

        $isOwner   = (int) $session->opened_by === (int) $user->id;
        $isAuditor = $this->hasAtLeast($user, RoleName::Admin);

        return $isOwner || $isAuditor
            ? Response::allow()
            : Response::denyWithStatus(403, 'Solo quien abrió la sesión o un Administrador puede cerrarla.');
    }
}
