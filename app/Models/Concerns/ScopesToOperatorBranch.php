<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Fase 5+ · Aislamiento de SUCURSAL para ROL-03 en los LISTADOS (consultas).
 *
 * Complementa a InteractsWithTenant::operatorInBranch (que protege el detalle y las mutaciones en las
 * Policies): la Policy de un índice (viewAny) no puede filtrar filas, por lo que el scope de la consulta
 * es la segunda mitad obligatoria del aislamiento. Punto ÚNICO y comprobable: cada controlador llama
 * `->forOperator($user)` sobre el modelo y la lógica de sucursal vive aquí (y en el override del modelo),
 * no repartida como `where('branch_id')` inconsistentes por decenas de controladores.
 *
 * Contrato:
 *  - ROL-01/ROL-02 (no operativos): sin restricción adicional → alcance de negocio (BusinessScope).
 *  - ROL-03 con sucursal: solo filas de SU sucursal (resolución directa o por relación, según el modelo).
 *  - ROL-03 sin sucursal: NINGUNA fila (cierre en falso), coherente con operatorInBranch.
 */
trait ScopesToOperatorBranch
{
    public function scopeForOperator(Builder $query, User $user): Builder
    {
        if (! $user->isOperator()) {
            return $query; // ROL-01/ROL-02: alcance de negocio.
        }

        $branchId = $user->branch_id;

        if ($branchId === null) {
            return $query->whereRaw('1 = 0'); // Operador sin sucursal: no ve nada.
        }

        return $this->applyOperatorBranchScope($query, (int) $branchId);
    }

    /**
     * Resolución por DEFECTO: columna directa `branch_id`. Los modelos cuya sucursal es INDIRECTA
     * (por bodega, factura u orden) sobrescriben este método con el whereHas correspondiente.
     */
    protected function applyOperatorBranchScope(Builder $query, int $branchId): Builder
    {
        return $query->where($this->qualifyColumn('branch_id'), $branchId);
    }
}
