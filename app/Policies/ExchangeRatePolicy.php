<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\RoleName;
use App\Models\ExchangeRate;
use App\Models\User;
use App\Policies\Concerns\InteractsWithTenant;
use Illuminate\Auth\Access\Response;

/**
 * MOD-06 · El tipo de cambio es un parámetro financiero sensible: su administración (consulta del
 * historial y registro de nuevas vigencias) es potestad de ROL-01/ROL-02 (al menos Administrador).
 * No puede depender de ediciones manuales en la base de datos. El historial es inmutable: no hay
 * update ni delete (una corrección se expresa registrando una nueva vigencia).
 */
final class ExchangeRatePolicy
{
    use InteractsWithTenant;

    public function viewAny(User $actor): Response
    {
        return $this->hasAtLeast($actor, RoleName::Admin)
            ? Response::allow()
            : Response::deny('Solo ROL-01/ROL-02 pueden consultar el tipo de cambio.');
    }

    public function view(User $actor, ExchangeRate $rate): Response
    {
        if (! $this->sharesBusinessWith($actor, $rate)) {
            return Response::denyWithStatus(404, 'El tipo de cambio indicado no pertenece a su negocio.');
        }

        return $this->hasAtLeast($actor, RoleName::Admin)
            ? Response::allow()
            : Response::deny('Solo ROL-01/ROL-02 pueden consultar el tipo de cambio.');
    }

    public function create(User $actor): Response
    {
        return $this->hasAtLeast($actor, RoleName::Admin)
            ? Response::allow()
            : Response::deny('Solo ROL-01/ROL-02 pueden administrar el tipo de cambio.');
    }
}
