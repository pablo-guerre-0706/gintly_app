<?php

declare(strict_types=1);

namespace App\Services\Purchasing;

use App\Enums\AccountPayableStatus;
use App\Exceptions\InvalidPurchaseStateException;
use App\Models\AccountPayable;
use App\Models\User;
use Illuminate\Support\Facades\DB;


final class AccountPayableService
{
    // Registra un abono. Rechaza si cuenta está congelada (409) o el monto excede el saldo (422).
    public function pagar(AccountPayable $payable, string $amount, ?string $dueDate): AccountPayable
    {
        return DB::transaction(function () use ($payable, $amount, $dueDate): AccountPayable {
            $payable = AccountPayable::query()->whereKey($payable->getKey())->lockForUpdate()->firstOrFail();

            if ($payable->status->isBlocked()) {
                throw InvalidPurchaseStateException::payableBlocked($payable->id);
            }

            $newPaid = bcadd((string) $payable->paid_amount, $amount, 2);

            // El abono no puede exceder el total adeudado.
            if (bccomp($newPaid, (string) $payable->total_amount, 2) > 0) {
                throw InvalidPurchaseStateException::paymentExceedsBalance($payable->id);
            }

            $payable->paid_amount = $newPaid;

            if ($dueDate !== null) {
                $payable->due_date = $dueDate;
            }

            // Recalcula estado: pagada si el saldo llega a cero, parcial si no.
            $payable->status = bccomp($newPaid, (string) $payable->total_amount, 2) === 0
                ? AccountPayableStatus::Pagada
                : AccountPayableStatus::Parcial;

            $payable->save();

            return $payable->refresh();
        });
    }

    public function descongelar(User $actor, AccountPayable $payable): AccountPayable
    {
        return DB::transaction(function () use ($actor, $payable): AccountPayable {
            $payable = AccountPayable::query()->whereKey($payable->getKey())->lockForUpdate()->firstOrFail();

            if (! $payable->status->isBlocked()) {
                // Idempotencia: descongelar algo no congelado no es un error grave,
                // pero se rechaza para no ocultar un flujo inconsistente.
                throw InvalidPurchaseStateException::payableNotBlocked($payable->id);
            }

            $payable->status = bccomp((string) $payable->paid_amount, '0', 2) > 0
                ? AccountPayableStatus::Parcial
                : AccountPayableStatus::Pendiente;
            $payable->unblocked_by = $actor->id;
            $payable->save();

            return $payable->refresh();
        });
    }
}
