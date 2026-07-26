<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\RoleName;
use App\Models\PhysicalCount;
use App\Models\User;
use App\Policies\Concerns\InteractsWithTenant;
use Illuminate\Auth\Access\Response;

final class PhysicalCountPolicy
{
    use InteractsWithTenant;

    public function viewAny(User $actor): Response
    {
        return $this->hasAtLeast($actor, RoleName::Operator)
            ? Response::allow()
            : Response::deny('No tiene autorización para consultar los conteos físicos.');
    }

    public function view(User $actor, PhysicalCount $count): Response
    {
        if (! $this->sharesBusinessWith($actor, $count)) {
            return Response::deny('El conteo solicitado no pertenece a su negocio.');
        }

        return $this->hasAtLeast($actor, RoleName::Admin)
            ? Response::allow()
            : Response::deny('No tiene autorización para consultar este conteo.');
    }

    // El conteo lo registra el operativo (ROL-03), está en el piso, cuenta.
    public function create(User $actor): Response
    {
        return $this->hasAtLeast($actor, RoleName::Operator)
            ? Response::allow()
            : Response::deny('No tiene autorización para registrar conteos físicos.');
    }

    // Aplicar (ajustar stock) y justificar son decisiones de ROL-02.
    public function apply(User $actor, PhysicalCount $count): Response
    {
        return $this->resolve($actor, $count);
    }

    public function justify(User $actor, PhysicalCount $count): Response
    {
        return $this->resolve($actor, $count);
    }

    private function resolve(User $actor, PhysicalCount $count): Response
    {
        if (! $this->sharesBusinessWith($actor, $count)) {
            return Response::deny('El conteo indicado no pertenece a su negocio.');
        }

        return $this->hasAtLeast($actor, RoleName::Admin)
            ? Response::allow()
            : Response::deny('Solo un administrador puede aplicar o justificar un conteo físico.');
    }
}
