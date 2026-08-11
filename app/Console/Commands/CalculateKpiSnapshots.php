<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\PeriodType;
use App\Models\Business;
use App\Services\Report\KpiService;
use Illuminate\Console\Command;

final class CalculateKpiSnapshots extends Command
{
    protected $signature = 'kpi:snapshot {--period=diario : Tipo de período a recalcular}';

    protected $description = 'Recalcula las instantáneas de KPI por negocio (RF-12-03). Recorre todos los tenants sin sesión.';

    public function handle(KpiService $kpis): int
    {
        $period = PeriodType::tryFrom((string) $this->option('period')) ?? PeriodType::Diario;

        Business::query()->each(function (Business $business) use ($kpis, $period): void {
            try {
                $result = $kpis->calcular($business->id, $period, null);
                $this->info("Negocio #{$business->id}: KPIs {$period->value} recalculados [{$result['period']['start']}..{$result['period']['end']}].");
            } catch (\Throwable $e) {
                report($e);
                $this->error("Negocio #{$business->id}: fallo al recalcular ({$e->getMessage()}).");
            }
        });

        return self::SUCCESS;
    }
}
