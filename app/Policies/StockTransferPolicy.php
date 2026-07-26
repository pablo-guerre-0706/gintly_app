<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\RoleName;
use App\Models\StockTransfer;
use App\Models\User;
use App\Policies\Concerns\InteractsWithTenant;
use Illuminate\Auth\Access\Response;

final class StockTransferPolicy
{
    use InteractsWithTenant;

    public function viewAny(User $actor): Response
    {
        return $this->hasAtLeast($actor, RoleName::Operator)
            ? Response::allow()
            : Response::deny('No tiene autorización para consultar los traspasos.');
    }

    public function view(User $actor, StockTransfer $transfer): Response
    {
        if (! $this->sharesBusinessWith($actor, $transfer)) {
            return Response::deny('El traspaso solicitado no pertenece a su negocio.');
        }

        return $this->hasAtLeast($actor, RoleName::Admin)
            ? Response::allow()
            : Response::deny('No tiene autorización para consultar este traspaso.');
    }

    // El traspaso lo inicia y lo completa el operativo (mueve físicamente).
    public function create(User $actor): Response
    {
        return $this->hasAtLeast($actor, RoleName::Operator)
            ? Response::allow()
            : Response::deny('No tiene autorización para registrar traspasos.');
    }

    public function complete(User $actor, StockTransfer $transfer): Response
    {
        if (! $this->sharesBusinessWith($actor, $transfer)) {
            return Response::deny('El traspaso indicado no pertenece a su negocio.');
        }

        return $this->hasAtLeast($actor, RoleName::Operator)
            ? Response::allow()
            : Response::deny('No tiene autorización para completar traspasos.');
    }

    // Cancelar es decisión de ROL-02: revierte un compromiso operativo.
    public function cancel(User $actor, StockTransfer $transfer): Response
    {
        if (! $this->sharesBusinessWith($actor, $transfer)) {
            return Response::deny('El traspaso indicado no pertenece a su negocio.');
        }

        return $this->hasAtLeast($actor, RoleName::Admin)
            ? Response::allow()
            : Response::deny('Solo un administrador puede cancelar un traspaso.');
    }
}
