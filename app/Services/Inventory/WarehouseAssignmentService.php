<?php

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Enums\OperativeProfile;
use App\Exceptions\WarehouseAssignmentConflictException;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseAssignment;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * MOD-03 · Administración de asignaciones Bodega–Bodeguero (ROL-01/ROL-02) y compuerta de OPERACIÓN para
 * ROL-03. Invariantes de dominio en el Service (no solo del FormRequest): misma sucursal, usuario ROL-03
 * con perfil bodeguero, par (bodega, usuario) activo único (respaldado por candado de motor). M:N: un
 * bodeguero puede tener varias bodegas y una bodega varios bodegueros. `assertOperates` es la compuerta
 * que TODO flujo de inventario del bodeguero debe superar: sin asignación activa, no opera la bodega.
 */
final class WarehouseAssignmentService
{
    public function assign(User $admin, int $warehouseId, int $userId): WarehouseAssignment
    {
        return DB::transaction(function () use ($admin, $warehouseId, $userId): WarehouseAssignment {
            $businessId = (int) $admin->business_id;

            $warehouse = Warehouse::query()
                ->where('business_id', $businessId)
                ->whereKey($warehouseId)
                ->lockForUpdate()
                ->firstOrFail();

            $keeper = User::query()
                ->where('business_id', $businessId)
                ->whereKey($userId)
                ->lockForUpdate()
                ->firstOrFail();

            $this->assertAssignable($warehouse, $keeper);
            $this->assertNoActiveDuplicate($businessId, $warehouse->id, $keeper->id);

            try {
                $assignment = new WarehouseAssignment([
                    'branch_id'    => $warehouse->branch_id,
                    'warehouse_id' => $warehouse->id,
                    'user_id'      => $keeper->id,
                    'assigned_by'  => $admin->id,
                    'assigned_at'  => Carbon::now(),
                ]);
                $assignment->save();
            } catch (QueryException $e) {
                if (($e->errorInfo[1] ?? null) === 1062) {
                    throw WarehouseAssignmentConflictException::alreadyAssigned();
                }
                throw $e;
            }

            return $assignment->refresh();
        });
    }

    /** Finaliza (cierra vigencia) una asignación activa. Idempotente si ya estaba finalizada. */
    public function finish(User $admin, WarehouseAssignment $assignment): WarehouseAssignment
    {
        return DB::transaction(function () use ($admin, $assignment): WarehouseAssignment {
            $fresh = WarehouseAssignment::query()
                ->whereKey($assignment->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if (! $fresh->isActive()) {
                return $fresh;
            }

            $fresh->ended_at = Carbon::now();
            $fresh->ended_by = $admin->id;
            $fresh->save();

            return $fresh->refresh();
        });
    }

    /**
     * Compuerta de operación: un ROL-03 (bodeguero) solo opera una bodega que tenga ACTIVAMENTE asignada.
     * ROL-01/ROL-02 no se acotan. Lanza AuthorizationException (403) cuando el bodeguero no la tiene asignada.
     */
    public function assertOperates(User $actor, int $warehouseId): void
    {
        if (! $actor->isOperator()) {
            return;
        }

        if (! $this->operates($actor, $warehouseId)) {
            throw new AuthorizationException('No tiene asignada esta bodega; solicite al administrador la asignación.');
        }
    }

    public function operates(User $actor, int $warehouseId): bool
    {
        if (! $actor->isOperator()) {
            return true;
        }

        return WarehouseAssignment::query()
            ->where('business_id', $actor->business_id)
            ->where('warehouse_id', $warehouseId)
            ->where('user_id', $actor->id)
            ->whereNull('ended_at')
            ->exists();
    }

    // ---------------- Invariantes ----------------

    private function assertAssignable(Warehouse $warehouse, User $keeper): void
    {
        if (! $keeper->isOperator() || ! $keeper->hasProfile(OperativeProfile::Bodeguero)) {
            throw ValidationException::withMessages([
                'user_id' => ['El usuario debe ser ROL-03 con perfil de bodeguero.'],
            ]);
        }

        $warehouseBranch = $warehouse->branch_id !== null ? (int) $warehouse->branch_id : null;
        $keeperBranch = $keeper->branch_id !== null ? (int) $keeper->branch_id : null;

        if ($warehouseBranch === null || $keeperBranch === null || $warehouseBranch !== $keeperBranch) {
            throw ValidationException::withMessages([
                'user_id' => ['La bodega y el bodeguero deben pertenecer a la misma sucursal.'],
            ]);
        }
    }

    private function assertNoActiveDuplicate(int $businessId, int $warehouseId, int $userId): void
    {
        $exists = WarehouseAssignment::query()
            ->where('business_id', $businessId)
            ->where('warehouse_id', $warehouseId)
            ->where('user_id', $userId)
            ->whereNull('ended_at')
            ->exists();

        if ($exists) {
            throw WarehouseAssignmentConflictException::alreadyAssigned();
        }
    }
}
