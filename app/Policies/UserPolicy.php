<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\RoleName;
use App\Models\User;
use App\Policies\Concerns\InteractsWithTenant;
use Illuminate\Auth\Access\Response;


// Precondición documental: "rol ROL-02 o superior".
// ROL-02 gestiona usuarios, ROL-01 es la máxima autoridad.
final class UserPolicy
{
    use InteractsWithTenant;

    public function viewAny(User $actor): Response
    {
        return $this->hasAtLeast($actor, RoleName::Admin)
            ? Response::allow()
            : Response::deny('No tiene autorización para consultar el listado de usuarios.');
    }

    // Método adicional: respalda GET /users/{user} del contrato MOD-01 V2.
    public function view(User $actor, User $target): Response
    {
        if (! $this->sharesBusinessWith($actor, $target)) {
            return Response::deny('El usuario solicitado no pertenece a su negocio.');
        }

        if ($this->isSelf($actor, $target)) {
            return Response::allow();
        }

        return $this->hasAtLeast($actor, RoleName::Admin)
            ? Response::allow()
            : Response::deny('No tiene autorización para consultar este usuario.');
    }

    public function create(User $actor): Response
    {
        return $this->hasAtLeast($actor, RoleName::Admin)
            ? Response::allow()
            : Response::deny('No tiene autorización para registrar usuarios.');
    }

    public function update(User $actor, User $target): Response
    {
        return $this->canManage($actor, $target);
    }

    // Permite cambiar el rol de un usuario, pero bloquea que un administrador cree o asigne
    // un puesto con más poder que el suyo para evitar hackeos.
    public function assignRole(User $actor, User $target, ?string $grantedRole = null): Response
    {
        $manage = $this->canManage($actor, $target);

        if ($manage->denied()) {
            return $manage;
        }

        // Doble candado. Duplica la guarda del UpdateUserRoleRequest:
        // la validación protege el borde, la Policy protege cualquier otra vía.
        if ($this->isSelf($actor, $target)) {
            return Response::deny('No puede modificar su propio rol.');
        }

        // El propietario registrado del negocio no cambia de rol por esta vía:
        // dejaría al tenant sin su autoridad máxima.
        if ($this->isBusinessOwner($target)) {
            return Response::deny('No es posible modificar el rol del propietario del negocio.');
        }

        if ($grantedRole !== null) {
            $granted = RoleName::tryFrom($grantedRole);

            if ($granted === null) {
                return Response::deny('El rol indicado no es un rol reconocido del sistema.');
            }

            // Antiescalada por proxy: nadie concede autoridad que no posee.
            if ($granted->level() > $this->levelOf($actor)) {
                return Response::deny('No puede asignar un rol de autoridad superior a la suya.');
            }
        }

        return Response::allow();
    }

    // Separa el cambio de clave propio (que te pide la contraseña actual) del reinicio 
    // que hace un administrador a otro usuario
    public function resetPassword(User $actor, User $target): Response
    {
        $manage = $this->canManage($actor, $target);

        if ($manage->denied()) {
            return $manage;
        }

        if ($this->isSelf($actor, $target)) {
            return Response::deny('Para cambiar su propia contraseña utilice la opción de cambio personal.');
        }

        return Response::allow();
    }

    
    //Por seguridad, solo un administrador puede cambiar el correo de un usuario para evitar robos de
    // cuenta sin verificación externa 
    public function updateEmail(User $actor, User $target): Response
    {
        return $this->canManage($actor, $target);
    }

    //Prohíbe borrar usuarios físicamente de la base de datos; solo permite
    // desactivarlos conservando su historial 
    public function delete(User $actor, User $target): Response
    {
        $manage = $this->canManage($actor, $target);

        if ($manage->denied()) {
            return $manage;
        }

        if ($this->isSelf($actor, $target)) {
            return Response::deny('No puede dar de baja su propia cuenta.');
        }

        if ($this->isBusinessOwner($target)) {
            return Response::deny('No es posible dar de baja al propietario del negocio.');
        }

        return Response::allow();
    }

    // Prohíbe que un usuario de menor rango edite o desactive a su jefe, evitando que un
    // administrador le robe el control al propietario.
    private function canManage(User $actor, User $target): Response
    {
        if (! $this->sharesBusinessWith($actor, $target)) {
            return Response::deny('El usuario indicado no pertenece a su negocio.');
        }

        if (! $this->hasAtLeast($actor, RoleName::Admin)) {
            return Response::deny('No tiene autorización para gestionar usuarios.');
        }

        if ($this->levelOf($target) > $this->levelOf($actor)) {
            return Response::deny('No puede gestionar a un usuario con autoridad superior a la suya.');
        }

        return Response::allow();
    }
}
