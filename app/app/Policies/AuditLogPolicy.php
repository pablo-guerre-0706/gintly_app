<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\RoleName;
use App\Models\AuditLog;
use App\Models\User;
use App\Policies\Concerns\InteractsWithTenant;
use Illuminate\Auth\Access\Response;


//Prohíbe borrar o editar el historial de auditoría de forma explícita en el 
// código, asegurando que la bitácora sea 100% inmutable y segura 
final class AuditLogPolicy
{
    use InteractsWithTenant;

    public function viewAny(User $actor): Response
    {
        return $this->hasAtLeast($actor, RoleName::Admin)
            ? Response::allow()
            : Response::deny('No tiene autorización para consultar la bitácora de auditoría.');
    }

    public function view(User $actor, AuditLog $auditLog): Response
    {
        if (! $this->sharesBusinessWith($actor, $auditLog)) {
            return Response::deny('El registro solicitado no pertenece a su negocio.');
        }

        return $this->hasAtLeast($actor, RoleName::Admin)
            ? Response::allow()
            : Response::deny('No tiene autorización para consultar la bitácora de auditoría.');
    }

    // El registro de auditoría lo maneja solo el sistema de forma automática,
    // sin que intervengan los usuarios ordinarios
    public function create(User $actor): Response
    {
        return Response::deny('La bitácora de auditoría solo la escribe el sistema.');
    }

    // ERR-01B · HTTP 403 · InmutableAuditException. */
    public function update(User $actor, AuditLog $auditLog): Response
    {
        return Response::deny('La bitácora de auditoría es inmutable: no admite modificación.');
    }

    // ERR-01B · HTTP 403 · InmutableAuditException. */
    public function delete(User $actor, AuditLog $auditLog): Response
    {
        return Response::deny('La bitácora de auditoría es inmutable: no admite eliminación.');
    }

    public function restore(User $actor, AuditLog $auditLog): Response
    {
        return Response::deny('La bitácora de auditoría no admite restauración.');
    }

    public function forceDelete(User $actor, AuditLog $auditLog): Response
    {
        return Response::deny('La bitácora de auditoría es inmutable: no admite eliminación.');
    }
}
