<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\RoleName;
use App\Models\SalesReturn;
use App\Models\User;
use App\Policies\Concerns\InteractsWithTenant;

final class SalesReturnPolicy
{
    use InteractsWithTenant;

    /** GET /sales-returns (ROL-01/02, o ROL-03 BODEGUERO con devoluciones.ver — acotado a su sucursal en el índice). */
    public function viewAny(User $user): bool
    {
        return $this->hasAtLeast($user, RoleName::Operator)
            && $this->operatorGrants($user, 'devoluciones.ver');
    }

    /** GET /sales-returns/{id} (ROL-01/02, o ROL-03 BODEGUERO de SU sucursal). */
    public function view(User $user, SalesReturn $salesReturn): bool
    {
        return $this->sharesBusinessWith($user, $salesReturn)
            && $this->hasAtLeast($user, RoleName::Operator)
            && $this->operatorGrants($user, 'devoluciones.ver')
            && $this->operatorInBranch($user, $salesReturn->branch_id);
    }

    /** GET /sales-returns/{id}/items — mismo alcance que view. */
    public function viewItems(User $user, SalesReturn $salesReturn): bool
    {
        return $this->view($user, $salesReturn);
    }

    /** POST /sales-returns — registrar devolución (ROL-03+). */
    // Fase 5: registrar devolución exige el perfil BODEGUERO (la sucursal la valida el servicio).
    public function create(User $user): bool
    {
        return $this->hasAtLeast($user, RoleName::Operator)
            && $this->operatorGrants($user, 'devoluciones.crear');
    }
}
