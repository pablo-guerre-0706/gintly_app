<?php

declare(strict_types=1);

namespace Tests\Concerns;

use App\Enums\OperativeProfile;
use App\Models\User;
use App\Models\UserOperativeProfile;

/**
 * Utilidad de pruebas (Fase 5): equipa a un operador ROL-03 con una sucursal y TODOS los perfiles
 * operativos, para que los tests de flujo de módulo ejerzan las operaciones (ahora gateadas por
 * perfil + sucursal). En producción los perfiles se asignan explícitamente; aquí solo es andamiaje
 * de prueba que reproduce el comportamiento previo (un ROL-03 capaz de operar el flujo del módulo).
 */
trait EquipsOperativeProfiles
{
    protected function equipOperator(User $operator, int $branchId, array $profiles = null): void
    {
        $operator->forceFill(['branch_id' => $branchId])->save();

        $operator->operativeProfiles()->delete();

        foreach ($profiles ?? OperativeProfile::values() as $profile) {
            $row = new UserOperativeProfile(['profile' => $profile]);
            $row->user_id     = $operator->id;
            $row->business_id = $operator->business_id;
            $row->assigned_by = $operator->id;
            $row->save();
        }

        $operator->load('operativeProfiles');
    }
}
