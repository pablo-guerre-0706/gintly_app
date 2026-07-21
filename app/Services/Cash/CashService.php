<?php

namespace App\Services\Cash;

use App\Enums\CashMovementCategory;
use App\Enums\CashMovementType;
use App\Enums\CashSessionStatus;
use App\Enums\PaymentMethod;
use App\Exceptions\NoActiveCashSessionException;
use App\Exceptions\UnreconciledCashClosingException;
use App\Models\CashMovement;
use App\Models\CashSession;
use DomainException;
use Illuminate\Support\Facades\DB;

class CashService
{
    /**
     * Apertura de caja (RF-06-03). Fondo inicial obligatorio >= 0.
     * Los candados de motor hacen el trabajo pesado: open_register_lock impide una segunda
     * sesión abierta en la misma caja; open_user_lock impide que el cajero abra dos cajas
     * a la vez (RF-06-01). Si chocan, MySQL rechaza y la transacción revierte.
     */
    public function abrir(int $cashRegisterId, string $openingAmount, int $userId): CashSession
    {
        if (bccomp($openingAmount, '0', 2) < 0) {
            throw new DomainException('El fondo inicial no puede ser negativo.');
        }

        return DB::transaction(fn (): CashSession => CashSession::create([
            'cash_register_id' => $cashRegisterId,
            'opened_by'        => $userId,
            'opening_amount'   => $openingAmount,
            'opened_at'        => now(),
            // status → default 'abierta'
        ]));
    }

    /**
     * Registra un movimiento de caja (RF-06-01). Exige sesión ABIERTA (ERR-06).
     * Valida coherencia type⇄category (dominio) y autorización de egresos.
     */
    public function registrarMovimiento(
        int $sessionId,
        CashMovementType $type,
        CashMovementCategory $category,
        PaymentMethod $paymentMethod,
        string $amount,
        int $userId,
        ?int $authorizedBy = null,
        ?int $saleId = null,
        ?string $description = null,
    ): CashMovement {
        if (bccomp($amount, '0', 2) <= 0) {
            throw new DomainException('El monto del movimiento debe ser mayor que cero.');
        }

        // Coherencia de dominio (#5): la categoría no puede contradecir su tipo.
        $forced = $category->forcedType();
        if ($forced !== null && $forced !== $type) {
            throw new DomainException("La categoría '{$category->value}' exige tipo '{$forced->value}'.");
        }

        if ($category->requiresAuthorization() && $authorizedBy === null) {
            throw new DomainException('Un egreso autorizado requiere el ID del autorizante (ROL-02).');
        }

        return DB::transaction(function () use ($sessionId, $type, $category, $paymentMethod, $amount, $userId, $authorizedBy, $saleId, $description) {
            // lockForUpdate serializa esta operación contra un cierre concurrente:
            // si el cierre gana el lock primero, aquí veremos status != abierta y abortamos.
            $session = CashSession::query()->whereKey($sessionId)->lockForUpdate()->first();

            if ($session === null || ! $session->status->isOpen()) {
                throw new NoActiveCashSessionException;   // ERR-06 / RF-06-02
            }

            return CashMovement::create([
                'cash_session_id' => $session->id,
                'user_id'         => $userId,
                'type'            => $type,
                'category'        => $category,
                'payment_method'  => $paymentMethod,
                'amount'          => $amount,
                'sale_id'         => $saleId,
                'authorized_by'   => $authorizedBy,
                'description'     => $description,
            ]);
        });
    }

    /**
     * Cierre con arqueo ciego (RF-06-04/05). Flujo:
     *  1) Persiste el conteo físico (total + denominaciones) — el cajero NUNCA vio el esperado.
     *  2) Calcula el esperado en EFECTIVO (solo el efectivo vive en la gaveta).
     *  3) La columna generada 'difference' se deriva sola (no editable por el cajero).
     *  4) Sin descuadre → 'cerrada'. Con descuadre → 'descuadrada' + anomalía (MOD-11) y 422.
     *
     * @throws UnreconciledCashClosingException|NoActiveCashSessionException
     */
    public function cerrar(
        int $sessionId,
        string $countedAmount,
        array $countedDenominations,
        int $userId,
        ?string $closingNotes = null,
    ): CashSession {
        // El desglose es obligatorio y su suma debe igualar el efectivo declarado.
        $this->assertDenominationsMatch($countedDenominations, $countedAmount);

        $session = DB::transaction(function () use ($sessionId, $countedAmount, $countedDenominations, $userId, $closingNotes) {
            $session = CashSession::query()->whereKey($sessionId)->lockForUpdate()->first();

            if ($session === null || ! $session->status->isOpen()) {
                throw new NoActiveCashSessionException('La sesión no está abierta o no existe.');
            }

            // (1) Conteo ciego: se guarda lo declarado.
            $session->counted_amount        = $countedAmount;
            $session->counted_denominations = $countedDenominations;

            // (2) Esperado en efectivo (solo la gaveta física).
            $session->expected_amount = $this->computeExpectedCash($session);

            // Diferencia calculada en PHP SOLO para decidir el estado; el motor la persiste
            // por su cuenta en la columna generada (misma fórmula, misma escala → sin divergencia).
            $diff = bcsub($countedAmount, $session->expected_amount, 2);

            $session->closed_by     = $userId;
            $session->closed_at     = now();
            $session->closing_notes = $closingNotes;
            $session->status = bccomp($diff, '0', 2) === 0
                ? CashSessionStatus::Cerrada
                : CashSessionStatus::Descuadrada;

            $session->save();

            if ($session->status === CashSessionStatus::Descuadrada) {
                $this->anomalies->registrarSilencioso(
                businessId: $session->business_id,
                code: \App\Enums\AnomalyRuleCode::DescuadreCaja,
                sourceType: 'cash_session',
                sourceId: $session->id,
                expected: (string) $session->expected_amount,
                actual: (string) $session->counted_amount,
                );
            }

            return $session;
        });

        // "Throw después del commit": la sesión descuadrada YA persistió (evidencia + futura anomalía).
        // El 422 solo señala al cajero que no hubo cierre limpio. NO revierte nada.
        if ($session->status === CashSessionStatus::Descuadrada) {
            throw new UnreconciledCashClosingException((string) $session->difference);
        }

        return $session;
    }

    // ─────── Helpers privados ───────

    /**
     * RF-06-05: esperado = fondo inicial + Σ(ingresos efectivo, sin fondo_inicial) − Σ(egresos efectivo).
     * Transferencia y tarjeta se ignoran: no tocan la gaveta física.
     */
    private function computeExpectedCash(CashSession $session): string
    {
        $expected = (string) $session->opening_amount;

        $cashMovements = $session->movements()
            ->where('payment_method', PaymentMethod::Efectivo->value)
            ->get(['type', 'category', 'amount']);

        foreach ($cashMovements as $m) {
            if (! $m->category->countsInExpected()) {   // excluye fondo_inicial (ya está en opening)
                continue;
            }
            $signed   = bcmul((string) $m->amount, (string) $m->type->signedFactor(), 2);
            $expected = bcadd($expected, $signed, 2);
        }

        return $expected;
    }

    /**
     * El desglose es obligatorio y su suma debe igualar el efectivo declarado.
     * Formato: [ ['value' => '500.00', 'qty' => 3], ['value' => '100.00', 'qty' => 10], ... ]
     */
    private function assertDenominationsMatch(array $denominations, string $countedAmount): void
    {
        if ($denominations === []) {
            throw new DomainException('El desglose de denominaciones es obligatorio para cerrar (arqueo ciego).');
        }

        $sum = '0.00';
        foreach ($denominations as $d) {
            $sum = bcadd($sum, bcmul((string) $d['value'], (string) $d['qty'], 2), 2);
        }

        if (bccomp($sum, $countedAmount, 2) !== 0) {
            throw new DomainException("El total declarado ({$countedAmount}) no coincide con el desglose ({$sum}).");
        }
    }
}
