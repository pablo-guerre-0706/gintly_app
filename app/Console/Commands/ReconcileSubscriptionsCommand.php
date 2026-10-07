<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Exceptions\BillingUnavailableException;
use App\Services\Billing\SubscriptionService;
use Illuminate\Console\Command;

/**
 * MOD-SUB · Reconciliación de suscripciones (la ejecuta el scheduler existente; SIN colas nuevas y SIN depender
 * del retorno del navegador). En una sola pasada: vence vigencias pasadas, aplica descensos programados cuyo
 * período cerró, reproduce eventos propios aparcados y recupera pagos cuyo webhook se perdió mediante consulta
 * oficial al proveedor. Fail-closed ante modo incoherente (no toca nada y reporta).
 */
final class ReconcileSubscriptionsCommand extends Command
{
    protected $signature = 'billing:reconcile';

    protected $description = 'Vence, aplica descensos programados, reproduce webhooks aparcados y recupera pagos perdidos (MOD-SUB).';

    public function handle(SubscriptionService $subscriptions): int
    {
        try {
            $result = $subscriptions->reconcile();
        } catch (BillingUnavailableException $e) {
            // Modo/credenciales incoherentes: no se arriesga ninguna mutación comercial.
            $this->warn('Reconciliación omitida: configuración de cobro no disponible.');

            return self::SUCCESS;
        }

        $this->info(sprintf(
            'Reconciliación: vencidas=%d, descensos=%d, webhooks reproducidos=%d, pagos recuperados=%d.',
            $result['expired'],
            $result['downgraded'],
            $result['replayed'],
            $result['recovered'],
        ));

        return self::SUCCESS;
    }
}
