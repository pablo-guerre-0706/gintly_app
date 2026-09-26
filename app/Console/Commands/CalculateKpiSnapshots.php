<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\PeriodType;
use App\Models\Business;
use App\Services\Report\KpiService;
use Illuminate\Console\Command;

/**
 * RF-12-03 · Recálculo programado de instantáneas de KPI. Recorre TODOS los tenants operativos
 * (no suspendidos ni eliminados) SIN sesión: el business_id sale del negocio en proceso, jamás de
 * Auth. Cada negocio se recalcula de forma aislada, atómica e idempotente (transacción/upsert en
 * KpiService); un fallo aislado no detiene al resto ni deja snapshots a medias, pero el comando
 * termina con código distinto de cero para que el scheduler lo reporte.
 */
final class CalculateKpiSnapshots extends Command
{
    protected $signature = 'kpi:snapshot {--period=diario : Tipo de período a recalcular (diario|semanal|mensual|anual)}';

    protected $description = 'Recalcula las instantáneas de KPI por negocio (RF-12-03). Recorre todos los tenants operativos sin sesión.';

    public function handle(KpiService $kpis): int
    {
        $period = PeriodType::tryFrom((string) $this->option('period'));

        if ($period === null) {
            $this->error("Período inválido '{$this->option('period')}'. Use: diario|semanal|mensual|anual.");

            return self::INVALID;
        }

        $failures = 0;

        Business::query()
            ->where('status', '!=', 'suspended') // SoftDeletes excluye los eliminados; se omiten los suspendidos.
            ->each(function (Business $business) use ($kpis, $period, &$failures): void {
                try {
                    $result = $kpis->calcular($business->id, $period, null);
                    $this->info("Negocio #{$business->id}: KPIs {$period->value} recalculados [{$result['period']['start']}..{$result['period']['end']}].");
                } catch (\Throwable $e) {
                    report($e);
                    ++$failures;
                    $this->error("Negocio #{$business->id}: fallo al recalcular ({$e->getMessage()}).");
                }
            });

        if ($failures > 0) {
            $this->error("{$failures} negocio(s) fallaron al recalcular KPIs {$period->value}.");

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
