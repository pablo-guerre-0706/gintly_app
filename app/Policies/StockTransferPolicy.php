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

    // El traspaso lo inicia el BODEGUERO; el ORIGEN debe ser de su sucursal (lo valida el servicio).
    public function create(User $actor): Response
    {
        return $this->hasAtLeast($actor, RoleName::Operator) && $this->operatorGrants($actor, 'inventario.traspaso')
            ? Response::allow()
            : Response::deny('No tiene autorización para registrar traspasos.');
    }

    // La finalización corresponde a un BODEGUERO de la sucursal RECEPTORA (destino).
    public function complete(User $actor, StockTransfer $transfer): Response
    {
        if (! $this->sharesBusinessWith($actor, $transfer)) {
            return Response::deny('El traspaso indicado no pertenece a su negocio.');
        }

        if (! $this->hasAtLeast($actor, RoleName::Operator) || ! $this->operatorGrants($actor, 'inventario.traspaso')) {
            return Response::deny('No tiene autorización para completar traspasos.');
        }

        // Fase 5: la sucursal receptora (bodega destino) debe ser la del operador.
        return $this->operatorInBranch($actor, $transfer->toWarehouse?->branch_id)
            ? Response::allow()
            : Response::deny('Solo un operador de la sucursal receptora puede completar el traspaso.');
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
