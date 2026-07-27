<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\RoleName;
use App\Models\PurchaseOrder;
use App\Models\User;
use App\Policies\Concerns\InteractsWithTenant;
use Illuminate\Auth\Access\Response;

final class PurchaseOrderPolicy
{
    use InteractsWithTenant;

    public function viewAny(User $actor): Response
    {
        return $this->hasAtLeast($actor, RoleName::Admin)
            ? Response::allow()
            : Response::deny('No tiene autorización para consultar las órdenes de compra.');
    }

    public function view(User $actor, PurchaseOrder $order): Response
    {
        if (! $this->sharesBusinessWith($actor, $order)) {
            return Response::deny('La orden solicitada no pertenece a su negocio.');
        }

        return $this->hasAtLeast($actor, RoleName::Admin)
            ? Response::allow()
            : Response::deny('No tiene autorización para consultar esta orden.');
    }

    public function create(User $actor): Response
    {
        return $this->hasAtLeast($actor, RoleName::Admin)
            ? Response::allow()
            : Response::deny('No tiene autorización para registrar órdenes de compra.');
    }

    public function update(User $actor, PurchaseOrder $order): Response
    {
        return $this->manage($actor, $order, 'modificar');
    }

    public function issue(User $actor, PurchaseOrder $order): Response
    {
        return $this->manage($actor, $order, 'emitir');
    }

    public function cancel(User $actor, PurchaseOrder $order): Response
    {
        return $this->manage($actor, $order, 'cancelar');
    }

    private function manage(User $actor, PurchaseOrder $order, string $verbo): Response
    {
        if (! $this->sharesBusinessWith($actor, $order)) {
            return Response::deny('La orden indicada no pertenece a su negocio.');
        }

        return $this->hasAtLeast($actor, RoleName::Admin)
            ? Response::allow()
            : Response::deny("No tiene autorización para {$verbo} órdenes de compra.");
    }
}
