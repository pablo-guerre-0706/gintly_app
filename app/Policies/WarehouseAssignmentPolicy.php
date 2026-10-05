<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\RoleName;
use App\Models\User;
use App\Models\WarehouseAssignment;
use App\Policies\Concerns\InteractsWithTenant;
use Illuminate\Auth\Access\Response;

/**
 * MOD-03 · Administración de asignaciones Bodega–Bodeguero: potestad de ROL-01/ROL-02. ROL-03 solo puede
 * CONSULTAR las suyas (viewAny; el controlador lo acota a sus filas). Finalizar (delete) exige ROL-02+.
 */
final class WarehouseAssignmentPolicy
{
    use InteractsWithTenant;

    public function viewAny(User $actor): Response
    {
        return $this->hasAtLeast($actor, RoleName::Operator)
            ? Response::allow()
            : Response::deny('No tiene autorización para consultar las asignaciones de bodega.');
    }

    public function create(User $actor): Response
    {
        return $this->hasAtLeast($actor, RoleName::Admin)
            ? Response::allow()
            : Response::deny('Solo ROL-01/ROL-02 pueden asignar bodegas a bodegueros.');
    }

    public function delete(User $actor, WarehouseAssignment $assignment): Response
    {
        if (! $this->sharesBusinessWith($actor, $assignment)) {
            return Response::denyWithStatus(404, 'La asignación indicada no pertenece a su negocio.');
        }

        return $this->hasAtLeast($actor, RoleName::Admin)
            ? Response::allow()
            : Response::deny('Solo ROL-01/ROL-02 pueden finalizar asignaciones de bodega.');
    }
}
