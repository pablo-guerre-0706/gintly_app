<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\RoleName;
use App\Models\Branch;
use App\Models\User;
use App\Policies\Concerns\InteractsWithTenant;
use Illuminate\Auth\Access\Response;


// ROL-02 gestiona bodegas y estructura operativa, ROL-01 consulta consolidados.
// Acreditación obligatoria: dirección, responsable y fecha de apertura, exigida en FormRequest
final class BranchPolicy
{
    use InteractsWithTenant;

    public function viewAny(User $actor): Response
    {
        return $this->hasAtLeast($actor, RoleName::Admin)
            ? Response::allow()
            : Response::deny('No tiene autorización para consultar las sucursales.');
    }

    // Método adicional: respalda GET /branches/{branch}.
    public function view(User $actor, Branch $branch): Response
    {
        if (! $this->sharesBusinessWith($actor, $branch)) {
            return Response::deny('La sucursal solicitada no pertenece a su negocio.');
        }

        return $this->hasAtLeast($actor, RoleName::Admin)
            ? Response::allow()
            : Response::deny('No tiene autorización para consultar esta sucursal.');
    }

    public function create(User $actor): Response
    {
        return $this->hasAtLeast($actor, RoleName::Admin)
            ? Response::allow()
            : Response::deny('No tiene autorización para registrar sucursales.');
    }

    public function update(User $actor, Branch $branch): Response
    {
        // Mantiene doble candado de seguridad en las sucursales para evitar que se filtren
        // datos entre empresas, incluso si se desactiva el filtro global de Laravel por error
        if (! $this->sharesBusinessWith($actor, $branch)) {
            return Response::deny('La sucursal indicada no pertenece a su negocio.');
        }

        return $this->hasAtLeast($actor, RoleName::Admin)
            ? Response::allow()
            : Response::deny('No tiene autorización para modificar sucursales.');
    }

    // Permite el borrado lógico de sucursales. Si hay cajas o bodegas activas, la BD frena el
    // borrado automáticamente con error 409
    public function delete(User $actor, Branch $branch): Response
    {
        if (! $this->sharesBusinessWith($actor, $branch)) {
            return Response::deny('La sucursal indicada no pertenece a su negocio.');
        }

        return $this->hasAtLeast($actor, RoleName::Admin)
            ? Response::allow()
            : Response::deny('No tiene autorización para dar de baja sucursales.');
    }
}
