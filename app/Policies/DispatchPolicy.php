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

    /** GET /dispatches (ROL-03+). */
    public function viewAny(User $user): bool
    {
        return $this->hasAtLeast($user, RoleName::Operator);
    }

    /** GET /dispatches/{id} (ROL-03+, acotado a su sucursal cuando el operador la tenga). */
    public function view(User $user, Dispatch $dispatch): bool
    {
        return $this->sharesBusinessWith($user, $dispatch)
            && $this->hasAtLeast($user, RoleName::Operator)
            && $this->withinOperationalScope($user, $dispatch);
    }

    /**
     * GET /dispatches/{id}/items (ROL-03+). Es el detalle operativo del retiro que el
     * propio operador registra/consulta; se alinea con view (antes ROL-02+, reconciliado).
     */
    public function viewItems(User $user, Dispatch $dispatch): bool
    {
        return $this->view($user, $dispatch);
    }

    /** POST /dispatches — registrar retiro (ROL-03; la sucursal la valida el servicio). */
    public function create(User $user): bool
    {
        return $this->hasAtLeast($user, RoleName::Operator);
    }

    /** POST /dispatches/{id}/revert — reversión (ROL-02, RF-09-04). */
    public function revert(User $user, Dispatch $dispatch): bool
    {
        return $this->sharesBusinessWith($user, $dispatch)
            && $this->hasAtLeast($user, RoleName::Admin);
    }

    /**
     * Alcance de sucursal para consulta: ROL-01/ROL-02 ven todo el negocio; ROL-03 con
     * sucursal asignada solo la suya (coherente con el aislamiento del retiro); ROL-03 sin
     * sucursal conserva el alcance de negocio ("cuando corresponda").
     */
    private function withinOperationalScope(User $user, Dispatch $dispatch): bool
    {
        if ($this->hasAtLeast($user, RoleName::Admin)) {
            return true;
        }

        if ($user->branch_id === null) {
            return true;
        }

        return (int) $dispatch->branch_id === (int) $user->branch_id;
    }
}
