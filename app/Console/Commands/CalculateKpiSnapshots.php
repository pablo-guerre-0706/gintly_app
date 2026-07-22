<?php

namespace App\Console\Commands;

use App\Enums\KpiPeriodType;
use App\Models\Business;
use App\Services\Kpi\KpiService;
use Illuminate\Console\Command;

class CalculateKpiSnapshots extends Command
{
    protected $signature = 'kpi:snapshot {--period=diario}';
    protected $description = 'Recalcula los snapshots de KPI por negocio (RF-12, MOD-12).';

    public function handle(KpiService $service): int
    {
        $period = KpiPeriodType::from($this->option('period'));

        Business::query()->where('status', '!=', 'suspended')->chunkById(100, function ($businesses) use ($service, $period) {
            foreach ($businesses as $business) {
                $service->calcular($business->id, $period);   // sin auth: tenant explícito
                $this->info("KPIs {$period->value} recalculados para negocio {$business->id}.");
            }
        });

        return self::SUCCESS;
    }
}
