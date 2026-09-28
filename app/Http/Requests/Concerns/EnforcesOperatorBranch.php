<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

use App\Enums\RoleName;
use Illuminate\Contracts\Validation\Validator;

/**
 * Fase 5 · Alcance de sucursal en la ENTRADA para ROL-03. Un operador solo puede operar en su
 * propia sucursal: si envía un branch_id (o equivalente) distinto de su user.branch_id, se rechaza
 * con 422. ROL-01/ROL-02 no están acotados (operan a nivel de negocio). No confía en el branch_id
 * del navegador sin contrastarlo con el usuario autenticado.
 */
trait EnforcesOperatorBranch
{
    protected function assertOperatorBranch(Validator $validator, string $field = 'branch_id'): void
    {
        $user = $this->user();

        if ($user === null || $user->getRoleNames()->first() !== RoleName::Operator->value) {
            return; // Solo ROL-03 se acota a su sucursal.
        }

        $submitted = $this->input($field);

        if ($submitted !== null && (int) $submitted !== (int) $user->branch_id) {
            $validator->errors()->add($field, 'Solo puede operar en su propia sucursal.');
        }
    }
}
