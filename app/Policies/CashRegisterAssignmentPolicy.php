<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\RoleName;
use App\Models\CashRegisterAssignment;
use App\Models\User;
use App\Policies\Concerns\InteractsWithTenant;
use Illuminate\Auth\Access\Response;

/**
 * MOD-06 · La administración de asignaciones Caja–Cajero es potestad de ROL-01/ROL-02 (autoridad del rol
 * humano, sin perfiles). ROL-03 solo puede CONSULTAR su propia asignación (viewAny: alcance acotado en el
 * controlador a sus propias filas). No hay update/delete de historial salvo la finalización (manage).
 */
final class CashRegisterAssignmentPolicy
{
    use InteractsWithTenant;

    /** Listar: ROL-03 ve solo las suyas (el controlador acota); ROL-01/ROL-02 todas las del negocio. */
    public function viewAny(User $actor): Response
    {
        return $this->hasAtLeast($actor, RoleName::Operator)
            ? Response::allow()
            : Response::deny('No tiene autorización para consultar las asignaciones de caja.');
    }

    public function create(User $actor): Response
    {
        return $this->hasAtLeast($actor, RoleName::Admin)
            ? Response::allow()
            : Response::deny('Solo ROL-01/ROL-02 pueden asignar cajas a cajeros.');
    }

    /** Finalizar una asignación (fija ended_at). */
    public function delete(User $actor, CashRegisterAssignment $assignment): Response
    {
        if (! $this->sharesBusinessWith($actor, $assignment)) {
            return Response::denyWithStatus(404, 'La asignación indicada no pertenece a su negocio.');
        }

        return $this->hasAtLeast($actor, RoleName::Admin)
            ? Response::allow()
            : Response::deny('Solo ROL-01/ROL-02 pueden finalizar asignaciones de caja.');
    }
}
