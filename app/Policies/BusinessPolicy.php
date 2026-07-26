<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\RoleName;
use App\Models\Business;
use App\Models\User;
use App\Policies\Concerns\InteractsWithTenant;
use Illuminate\Auth\Access\Response;


//Solo el propietario (ROL-01) puede cambiar el IVA y el huso horario [H-11].
// Se bloquea por completo la edición del estado de pago del negocio para evitar hackeos
final class BusinessPolicy
{
    use InteractsWithTenant;

    public function view(User $actor, Business $business): Response
    {
        if (! $this->sharesBusinessWith($actor, $business)) {
            return Response::deny('El negocio solicitado no corresponde a su sesión.');
        }

        return $this->hasAtLeast($actor, RoleName::Owner)
            ? Response::allow()
            : Response::deny('No tiene autorización para consultar la configuración del negocio.');
    }

    public function update(User $actor, Business $business): Response
    {
        // sharesBusinessWith() compara contra la PK cuando el recurso es
        // Business: es la raíz del tenant y no tiene columna business_id.
        if (! $this->sharesBusinessWith($actor, $business)) {
            return Response::deny('El negocio indicado no corresponde a su sesión.');
        }

        if (! $this->hasAtLeast($actor, RoleName::Owner)) {
            return Response::deny('La configuración del negocio es potestad exclusiva del propietario.');
        }

        // Un negocio suspendido conserva la lectura pero no admite cambios de
        // configuración: permitirlos dejaría sin efecto la suspensión.
        if ($business->status === 'suspended') {
            return Response::deny('El negocio se encuentra suspendido; su configuración no admite cambios.');
        }

        return Response::allow();
    }

    // El registro de nuevas empresas lo hace el sistema automáticamente, no los usuarios ordinarios
    public function create(User $actor): Response
    {
        return Response::deny('El registro de negocios no se realiza desde esta interfaz.');
    }

    // El negocio nunca se elimina, se suspende
    public function delete(User $actor, Business $business): Response
    {
        return Response::deny('Un negocio no se elimina: su baja se gestiona como suspensión de la plataforma.');
    }

    public function forceDelete(User $actor, Business $business): Response
    {
        return Response::deny('Un negocio no admite eliminación física.');
    }
}
