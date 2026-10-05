<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * MOD-03 (microcierre) · Aislamiento de BODEGA ASIGNADA para ROL-03 en los LISTADOS de inventario.
 *
 * Complementa —y es MÁS ESTRICTO que— ScopesToOperatorBranch: aquel acota un ROL-03 a su SUCURSAL, pero
 * dentro de una misma sucursal pueden convivir varias bodegas. La visibilidad de inventario de un bodeguero
 * debe limitarse a las bodegas que tiene ACTIVAMENTE asignadas (par bodega–bodeguero vigente), igual que la
 * compuerta de OPERACIÓN (WarehouseAssignmentService::assertOperates). Sin esto, dos bodegas de la misma
 * sucursal se filtraban entre sí en las lecturas aunque el bodeguero solo operara una.
 *
 * Contrato:
 *  - ROL-01/ROL-02 (no operativos): sin restricción adicional → alcance de negocio (BusinessScope).
 *  - ROL-03 con asignaciones activas: solo filas de SUS bodegas asignadas.
 *  - ROL-03 sin asignaciones (o sin sucursal): NINGUNA fila (cierre en falso), coherente con assertOperates.
 */
trait ScopesToAssignedWarehouses
{
    public function scopeForOperatorWarehouses(Builder $query, User $user): Builder
    {
        if (! $user->isOperator()) {
            return $query; // ROL-01/ROL-02: alcance de negocio.
        }

        $warehouseIds = $user->activeAssignedWarehouseIds();

        if ($warehouseIds === []) {
            return $query->whereRaw('1 = 0'); // Bodeguero sin asignación activa: no ve nada.
        }

        return $this->applyAssignedWarehouseScope($query, $warehouseIds);
    }

    /**
     * Resolución por DEFECTO: columna directa `warehouse_id`. Los modelos cuya bodega es INDIRECTA
     * sobrescriben este método con el whereHas correspondiente.
     *
     * @param  array<int, int>  $warehouseIds
     */
    protected function applyAssignedWarehouseScope(Builder $query, array $warehouseIds): Builder
    {
        return $query->whereIn($this->qualifyColumn('warehouse_id'), $warehouseIds);
    }
}
