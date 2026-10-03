<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\RoleName;
use App\Models\Dispatch;
use App\Models\User;
use App\Policies\Concerns\InteractsWithTenant;

final class DispatchPolicy
{
    use InteractsWithTenant; // before() fail-closed + hasAtLeast() + sharesBusinessWith().

    /** GET /dispatches (ROL-03 con perfil DESPACHADOR: entregas.ver; o ROL-01/ROL-02). */
    public function viewAny(User $user): bool
    {
        return $this->hasAtLeast($user, RoleName::Operator)
            && $this->operatorGrants($user, 'entregas.ver');
    }

    /** GET /dispatches/{id} (ROL-03+, acotado a su sucursal cuando el operador la tenga). */
    public function view(User $user, Dispatch $dispatch): bool
    {
        return $this->sharesBusinessWith($user, $dispatch)
            && $this->hasAtLeast($user, RoleName::Operator)
            && $this->operatorGrants($user, 'entregas.ver')
            && $this->operatorInBranch($user, $dispatch->branch_id); // ROL-03 solo su sucursal; sin sucursal, cerrado.
    }

    /**
     * GET /dispatches/{id}/items (ROL-03+). Es el detalle operativo del retiro que el
     * propio operador registra/consulta; se alinea con view (antes ROL-02+, reconciliado).
     */
    public function viewItems(User $user, Dispatch $dispatch): bool
    {
        return $this->view($user, $dispatch);
    }

    /** POST /dispatches — registrar retiro (ROL-03 con perfil DESPACHADOR; la sucursal la valida el servicio). */
    public function create(User $user): bool
    {
        return $this->hasAtLeast($user, RoleName::Operator)
            && $this->operatorGrants($user, 'entregas.crear');
    }

    /** POST /dispatches/{id}/revert — reversión (ROL-02, RF-09-04). */
    public function revert(User $user, Dispatch $dispatch): bool
    {
        return $this->sharesBusinessWith($user, $dispatch)
            && $this->hasAtLeast($user, RoleName::Admin);
    }
}
