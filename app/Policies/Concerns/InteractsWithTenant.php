<?php

declare(strict_types=1);

namespace App\Policies\Concerns;

use App\Enums\RoleName;
use App\Models\Business;
use App\Models\User;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;


// Evita que usuarios cambien el ID en la URL para robar o ver datos de otras empresas.
// Exige activar el middleware de roles por negocio para que el sistema no se bloquee por seguridad.

trait InteractsWithTenant
{
    
    // Bloquea de inmediato a usuarios suspendidos o sin negocio activo, pero no da acceso
    // automáticos para evitar fallos de seguridad.
    
    public function before(User $actor, string $ability): ?Response
    {
        if (! $actor->is_active) {
            return Response::deny('Su cuenta está desactivada.');
        }

        // un usuario sin rol asignado no opera en el sistema.
        if ($this->roleOf($actor) === null) {
            return Response::deny('Su cuenta no tiene un rol asignado. Contacte al administrador.');
        }

        return null;
    }

    protected function roleOf(User $actor): ?RoleName
    {
        $name = $actor->getRoleNames()->first();

        return $name === null ? null : RoleName::tryFrom((string) $name);
    }

    protected function levelOf(User $actor): int
    {
        return $this->roleOf($actor)?->level() ?? 0;
    }

    protected function hasAtLeast(User $actor, RoleName $minimum): bool
    {
        return $this->roleOf($actor)?->atLeast($minimum) ?? false;
    }

    
    // Compara el negocio del usuario directamente contra la empresa dueña 
    //de la sesión para evitar mezclar datos    
    protected function sharesBusinessWith(User $actor, Model $resource): bool
    {
        $resourceBusinessId = $resource instanceof Business
            ? $resource->getKey()
            : $resource->getAttribute('business_id');

        return $resourceBusinessId !== null
            && (int) $resourceBusinessId === (int) $actor->business_id;
    }

    protected function isSelf(User $actor, User $target): bool
    {
        return (int) $actor->getKey() === (int) $target->getKey();
    }

    
    // Identifica al dueño original de la empresa para evitar que se elimine la
    // cuenta principal del negocio
    protected function isBusinessOwner(User $target): bool
    {
        $ownerId = $target->business?->owner_user_id;

        return $ownerId !== null && (int) $ownerId === (int) $target->getKey();
    }
}
