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

    protected function isOperator(User $actor): bool
    {
        return $this->roleOf($actor) === RoleName::Operator;
    }

    /**
     * Fase 5 · Compuerta de PERFIL para ROL-03 (autorización ADITIVA). Si el actor es ROL-03, exige
     * que ALGÚN perfil asignado habilite la capacidad (operativeCan, fuente config/profiles.php); un
     * ROL-03 sin perfiles queda bloqueado (opción B). ROL-01/ROL-02 no requieren perfil: su autoridad
     * es el rol humano. IMPORTANTE: no basta con que el rol Spatie contenga la unión de permisos; el
     * ROL-03 debe superar operativeCan().
     */
    protected function operatorGrants(User $actor, string $capability): bool
    {
        return $this->isOperator($actor) ? $actor->operativeCan($capability) : true;
    }

    /**
     * Fase 5 · Alcance de SUCURSAL para ROL-03 (autorización ADITIVA). Si el actor es ROL-03, el
     * recurso debe pertenecer a su propia sucursal (user.branch_id); ROL-01/ROL-02 operan a nivel de
     * negocio. Un ROL-03 sin branch_id nunca supera el alcance.
     */
    protected function operatorInBranch(User $actor, ?int $branchId): bool
    {
        if (! $this->isOperator($actor)) {
            return true;
        }

        return $branchId !== null
            && $actor->branch_id !== null
            && (int) $branchId === (int) $actor->branch_id;
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
