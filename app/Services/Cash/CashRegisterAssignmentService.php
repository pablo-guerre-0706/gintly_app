<?php

declare(strict_types=1);

namespace App\Services\Cash;

use App\Enums\CashSessionStatus;
use App\Enums\OperativeProfile;
use App\Exceptions\CashAssignmentConflictException;
use App\Models\CashRegister;
use App\Models\CashRegisterAssignment;
use App\Models\CashSession;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * MOD-06 · Administración de asignaciones Caja–Cajero (ROL-01/ROL-02). Invariantes de dominio (no solo del
 * FormRequest): misma sucursal, usuario ROL-03 con perfil cajero, una caja por cajero y un cajero por caja
 * (respaldado por candados de motor), y prohibición de asignar/finalizar con una sesión abierta vinculada.
 * Transaccional + lockForUpdate para serializar asignaciones concurrentes. El historial es append-only:
 * reasignar es finalizar (finish) + asignar (assign), nunca modificar ni borrar filas previas.
 */
final class CashRegisterAssignmentService
{
    /**
     * Asigna un cajero a una caja. ESTRICTO: si la caja ya tiene cajero activo o el cajero ya tiene caja
     * activa, lanza 409 (reasignar exige finalizar antes). Rechaza si hay sesión abierta vinculada.
     */
    public function assign(User $admin, int $cashRegisterId, int $userId): CashRegisterAssignment
    {
        return DB::transaction(function () use ($admin, $cashRegisterId, $userId): CashRegisterAssignment {
            $businessId = (int) $admin->business_id;

            // Orden de bloqueo canónico: caja → usuario (serializa asignaciones concurrentes).
            $register = CashRegister::query()
                ->where('business_id', $businessId)
                ->whereKey($cashRegisterId)
                ->lockForUpdate()
                ->firstOrFail();

            $cashier = User::query()
                ->where('business_id', $businessId)
                ->whereKey($userId)
                ->lockForUpdate()
                ->firstOrFail();

            $this->assertAssignable($register, $cashier);
            $this->assertNoLinkedOpenSession($register, $cashier);
            $this->assertNoActiveConflict($businessId, $register->id, $cashier->id);

            try {
                $assignment = new CashRegisterAssignment([
                    'branch_id'        => $register->branch_id,
                    'cash_register_id' => $register->id,
                    'user_id'          => $cashier->id,
                    'assigned_by'      => $admin->id,
                    'assigned_at'      => Carbon::now(),
                ]);
                $assignment->save();
            } catch (QueryException $e) {
                // Backstop de los candados de motor por si una carrera supera el pre-chequeo.
                if ($this->isUniqueViolation($e)) {
                    throw str_contains($e->getMessage(), 'uniq_active_user_assignment')
                        ? CashAssignmentConflictException::userAssigned()
                        : CashAssignmentConflictException::registerAssigned();
                }
                throw $e;
            }

            return $assignment->refresh();
        });
    }

    /**
     * Finaliza (cierra la vigencia de) una asignación activa. Rechaza si la caja tiene una sesión abierta.
     * Idempotente: finalizar una ya finalizada la devuelve sin cambios.
     */
    public function finish(User $admin, CashRegisterAssignment $assignment): CashRegisterAssignment
    {
        return DB::transaction(function () use ($admin, $assignment): CashRegisterAssignment {
            $fresh = CashRegisterAssignment::query()
                ->whereKey($assignment->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if (! $fresh->isActive()) {
                return $fresh; // ya finalizada: nada que cerrar (historial intacto).
            }

            $register = CashRegister::query()->whereKey($fresh->cash_register_id)->lockForUpdate()->first();
            if ($register !== null && $this->registerHasOpenSession($register->id)) {
                throw CashAssignmentConflictException::openSession();
            }

            $fresh->ended_at = Carbon::now();
            $fresh->ended_by = $admin->id;
            $fresh->save();

            return $fresh->refresh();
        });
    }

    /** ¿El cajero tiene una asignación ACTIVA sobre ESTA caja? (consumida por CashService al abrir). */
    public function hasActiveAssignment(int $businessId, int $cashRegisterId, int $userId): bool
    {
        return CashRegisterAssignment::query()
            ->where('business_id', $businessId)
            ->where('cash_register_id', $cashRegisterId)
            ->where('user_id', $userId)
            ->whereNull('ended_at')
            ->exists();
    }

    // ---------------- Invariantes ----------------

    private function assertAssignable(CashRegister $register, User $cashier): void
    {
        if (! $register->is_active) {
            throw ValidationException::withMessages([
                'cash_register_id' => ['No se puede asignar una caja inactiva.'],
            ]);
        }

        if (! $cashier->isOperator() || ! $cashier->hasProfile(OperativeProfile::Cajero)) {
            throw ValidationException::withMessages([
                'user_id' => ['El usuario debe ser ROL-03 con perfil de cajero.'],
            ]);
        }

        $registerBranch = $register->branch_id !== null ? (int) $register->branch_id : null;
        $cashierBranch = $cashier->branch_id !== null ? (int) $cashier->branch_id : null;

        if ($registerBranch === null || $cashierBranch === null || $registerBranch !== $cashierBranch) {
            throw ValidationException::withMessages([
                'user_id' => ['La caja y el cajero deben pertenecer a la misma sucursal.'],
            ]);
        }
    }

    private function assertNoLinkedOpenSession(CashRegister $register, User $cashier): void
    {
        if ($this->registerHasOpenSession($register->id)) {
            throw CashAssignmentConflictException::openSession();
        }

        $userHasOpen = CashSession::query()
            ->where('opened_by', $cashier->id)
            ->where('status', CashSessionStatus::Abierta->value)
            ->exists();

        if ($userHasOpen) {
            throw CashAssignmentConflictException::openSession();
        }
    }

    private function assertNoActiveConflict(int $businessId, int $registerId, int $userId): void
    {
        $registerBusy = CashRegisterAssignment::query()
            ->where('business_id', $businessId)
            ->where('cash_register_id', $registerId)
            ->whereNull('ended_at')
            ->exists();

        if ($registerBusy) {
            throw CashAssignmentConflictException::registerAssigned();
        }

        $userBusy = CashRegisterAssignment::query()
            ->where('business_id', $businessId)
            ->where('user_id', $userId)
            ->whereNull('ended_at')
            ->exists();

        if ($userBusy) {
            throw CashAssignmentConflictException::userAssigned();
        }
    }

    private function registerHasOpenSession(int $registerId): bool
    {
        return CashSession::query()
            ->where('cash_register_id', $registerId)
            ->where('status', CashSessionStatus::Abierta->value)
            ->exists();
    }

    private function isUniqueViolation(QueryException $e): bool
    {
        return ($e->errorInfo[1] ?? null) === 1062;
    }
}
