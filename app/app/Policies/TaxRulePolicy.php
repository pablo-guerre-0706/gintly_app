<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\RoleName;
use App\Models\TaxRule;
use App\Models\User;
use App\Policies\Concerns\InteractsWithTenant;
use Illuminate\Auth\Access\Response;

/**
 * La configuración fiscal es potestad exclusiva del propietario (ROL-01): fija la
 * carga tributaria del negocio y no puede depender de cambios manuales en la BD.
 */
final class TaxRulePolicy
{
    use InteractsWithTenant;

    public function viewAny(User $actor): Response
    {
        return $this->hasAtLeast($actor, RoleName::Owner)
            ? Response::allow()
            : Response::deny('Solo el propietario puede consultar la configuración fiscal.');
    }

    public function view(User $actor, TaxRule $rule): Response
    {
        if (! $this->sharesBusinessWith($actor, $rule)) {
            return Response::denyWithStatus(404, 'La regla fiscal indicada no pertenece a su negocio.');
        }

        return $this->hasAtLeast($actor, RoleName::Owner)
            ? Response::allow()
            : Response::deny('Solo el propietario puede consultar la configuración fiscal.');
    }

    public function create(User $actor): Response
    {
        return $this->hasAtLeast($actor, RoleName::Owner)
            ? Response::allow()
            : Response::deny('Solo el propietario puede configurar reglas fiscales.');
    }

    public function update(User $actor, TaxRule $rule): Response
    {
        return $this->manage($actor, $rule);
    }

    public function delete(User $actor, TaxRule $rule): Response
    {
        return $this->manage($actor, $rule);
    }

    private function manage(User $actor, TaxRule $rule): Response
    {
        if (! $this->sharesBusinessWith($actor, $rule)) {
            return Response::denyWithStatus(404, 'La regla fiscal indicada no pertenece a su negocio.');
        }

        return $this->hasAtLeast($actor, RoleName::Owner)
            ? Response::allow()
            : Response::deny('Solo el propietario puede modificar reglas fiscales.');
    }
}
