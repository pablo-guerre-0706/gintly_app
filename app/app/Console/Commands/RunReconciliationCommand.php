<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\ReconciliationScope;
use App\Models\Business;
use App\Services\Anomaly\ReconciliationService;
use Illuminate\Console\Command;

final class RunReconciliationCommand extends Command
{
    protected $signature = 'reconciliation:run {--scope=integral : Alcance de la conciliación}';

    protected $description = 'Auditoría de fondo por negocio: detecta anomalías (RF-11-04). Recorre todos los tenants sin sesión.';

    public function handle(ReconciliationService $reconciliation): int
    {
        $scope = ReconciliationScope::tryFrom((string) $this->option('scope')) ?? ReconciliationScope::Integral;
        $total = 0;

        Business::query()->each(function (Business $business) use ($reconciliation, $scope, &$total): void {
            $run = $reconciliation->conciliar($business->id, $scope, null, 'programada', null);
            $total += $run->anomalies_found;

            $this->info("Negocio #{$business->id}: {$run->anomalies_found} anomalía(s) [{$run->status->value}].");
        });

        $this->info("Total de anomalías detectadas: {$total}.");

        return self::SUCCESS;
    }
}
