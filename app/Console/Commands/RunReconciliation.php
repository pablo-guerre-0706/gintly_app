<?php

namespace App\Console\Commands;

use App\Enums\ReconciliationRunType;
use App\Enums\ReconciliationScope;
use App\Models\Business;
use App\Services\Anomaly\ReconciliationService;
use Illuminate\Console\Command;

class RunReconciliation extends Command
{
    protected $signature = 'reconciliation:run {--scope=integral}';
    protected $description = 'Auditoría de fondo programada por negocio (RF-11-04).';

    public function handle(ReconciliationService $service): int
    {
        $scope = ReconciliationScope::from($this->option('scope'));

        // withoutGlobalScopes: el cron NO tiene auth() → recorre TODOS los tenants explícitamente.
        Business::query()->where('status', '!=', 'suspended')->chunkById(100, function ($businesses) use ($service, $scope) {
            foreach ($businesses as $business) {
                $service->ejecutar($business->id, $scope, ReconciliationRunType::Programada);
                $this->info("Conciliación {$scope->value} ejecutada para negocio {$business->id}.");
            }
        });

        return self::SUCCESS;
    }
}
