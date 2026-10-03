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

    // Consulta: ROL-01/ROL-02 y ROL-03 con perfil BODEGUERO (compras.ver), acotado a su sucursal en el detalle.
    public function viewAny(User $actor): Response
    {
        return $this->hasAtLeast($actor, RoleName::Operator) && $this->operatorGrants($actor, 'compras.ver')
            ? Response::allow()
            : Response::deny('No tiene autorización para consultar las órdenes de compra.');
    }

    public function view(User $actor, PurchaseOrder $order): Response
    {
        if (! $this->sharesBusinessWith($actor, $order)) {
            return Response::deny('La orden solicitada no pertenece a su negocio.');
        }

        if (! $this->hasAtLeast($actor, RoleName::Operator) || ! $this->operatorGrants($actor, 'compras.ver')) {
            return Response::deny('No tiene autorización para consultar esta orden.');
        }

        return $this->operatorInBranch($actor, $order->branch_id)
            ? Response::allow()
            : Response::deny('La orden pertenece a otra sucursal.');
    }

    // Crear un BORRADOR: ROL-03 con perfil BODEGUERO (compras.crear) o ROL-01/ROL-02. La sucursal se
    // valida en el FormRequest/Service. Emitir/cancelar/editar siguen siendo potestad de ROL-02 (manage()).
    public function create(User $actor): Response
    {
        return $this->hasAtLeast($actor, RoleName::Operator) && $this->operatorGrants($actor, 'compras.crear')
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
