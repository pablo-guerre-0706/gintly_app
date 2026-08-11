<?php

declare(strict_types=1);

namespace App\Services\Report;

use App\Enums\ReportType;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final class ReportService
{
    /**
     * RF-12-01 · Reporte consolidado, comparable y trazable (solo lectura).
     * TODA consulta filtra business_id explícitamente (ERR-12: las vistas son globales).
     * @return array{type:string, period:array{from:string,to:string}, totals:array, comparisons:array, series:array}
     */
    public function generar(int $businessId, ReportType $type, ?string $from, ?string $to, ?int $branchId): array
    {
        $end   = $to !== null ? CarbonImmutable::parse($to) : CarbonImmutable::now();
        $start = $from !== null ? CarbonImmutable::parse($from) : $end->startOfMonth();
        $span  = $start->diffInDays($end) + 1;
        $prevStart = $start->subDays($span);
        $prevEnd   = $start->subDay();

        $payload = match ($type) {
            ReportType::Ventas      => $this->ventas($businessId, $start, $end, $prevStart, $prevEnd, $branchId),
            ReportType::Cartera     => $this->cartera($businessId),
            ReportType::Inventario  => $this->inventario($businessId, $start, $end),
            ReportType::Caja        => $this->caja($businessId, $start, $end, $branchId),
            ReportType::Consolidado => $this->consolidado($businessId, $start, $end, $prevStart, $prevEnd, $branchId),
        };

        return array_merge(
            ['type' => $type->value, 'period' => ['from' => $start->toDateString(), 'to' => $end->toDateString()]],
            $payload,
        );
    }

    private function ventas(int $b, CarbonImmutable $s, CarbonImmutable $e, CarbonImmutable $ps, CarbonImmutable $pe, ?int $branchId): array
    {
        $q = fn (CarbonImmutable $a, CarbonImmutable $z) => DB::table('vw_kpi_ventas')->where('business_id', $b)
            ->whereBetween('day', [$a->toDateString(), $z->toDateString()])
            ->when($branchId !== null, fn ($x) => $x->where('branch_id', $branchId));

        $current  = (string) (clone $q($s, $e))->sum('total_sold');
        $previous = (string) (clone $q($ps, $pe))->sum('total_sold');
        $count    = (int) (clone $q($s, $e))->sum('invoice_count');

        $series = $q($s, $e)->selectRaw('day, SUM(total_sold) AS total_sold, SUM(invoice_count) AS invoice_count')
            ->groupBy('day')->orderBy('day')->get()
            ->map(fn ($r) => ['day' => $r->day, 'total_sold' => (string) $r->total_sold, 'invoice_count' => (int) $r->invoice_count])
            ->all();

        return [
            'totals'      => [
                'total_sold'    => bcadd($current, '0.00', 2),
                'invoice_count' => $count,
                'avg_ticket'    => $count > 0 ? bcdiv($current, (string) $count, 2) : '0.00',
            ],
            'comparisons' => [
                'previous_total' => bcadd($previous, '0.00', 2),
                'delta'          => bcsub($current === '' ? '0' : $current, $previous === '' ? '0' : $previous, 2),
            ],
            'series'      => $series,
        ];
    }

    private function cartera(int $b): array
    {
        $row = DB::table('vw_kpi_cartera')->where('business_id', $b)->first();

        return [
            'totals'      => [
                'emitida'    => bcadd((string) ($row->emitida ?? '0'), '0.00', 2),
                'recuperada' => bcadd((string) ($row->recuperada ?? '0'), '0.00', 2),
                'pendiente'  => bcadd((string) ($row->pendiente ?? '0'), '0.00', 2),
                'vencida'    => bcadd((string) ($row->vencida ?? '0'), '0.00', 2),
            ],
            'comparisons' => [],
            'series'      => [],
        ];
    }

    private function inventario(int $b, CarbonImmutable $s, CarbonImmutable $e): array
    {
        $exact = DB::table('vw_kpi_exactitud_stock')->where('business_id', $b)
            ->whereBetween('day', [$s->toDateString(), $e->toDateString()])
            ->selectRaw('COALESCE(SUM(abs_deviation),0) abs_dev, COALESCE(SUM(system_total),0) sys')->first();

        $short = (string) DB::table('vw_kpi_faltantes')->where('business_id', $b)
            ->whereBetween('day', [$s->toDateString(), $e->toDateString()])->sum('unjustified_shortage');

        $absDev = (string) ($exact->abs_dev ?? '0');
        $sys    = (string) ($exact->sys ?? '0');

        return [
            'totals'      => [
                'abs_deviation'        => bcadd($absDev, '0.000', 3),
                'exactitud_pct'        => bccomp($sys, '0', 3) > 0 ? bcmul(bcsub('1', bcdiv($absDev, $sys, 6), 6), '100', 2) : '100.00',
                'unjustified_shortage' => bcadd($short === '' ? '0' : $short, '0.00', 2),
            ],
            'comparisons' => [],
            'series'      => [],
        ];
    }

    private function caja(int $b, CarbonImmutable $s, CarbonImmutable $e, ?int $branchId): array
    {
        $row = DB::table('cash_sessions')->where('business_id', $b)
            ->where('status', 'cerrada')
            ->whereBetween('created_at', [$s->startOfDay(), $e->endOfDay()])
            ->when($branchId !== null, fn ($x) => $x->where('branch_id', $branchId))
            ->selectRaw('COUNT(*) sessions, COALESCE(SUM(ABS(difference)),0) abs_diff, COALESCE(SUM(counted_amount),0) counted')
            ->first();

        return [
            'totals'      => [
                'sessions'       => (int) ($row->sessions ?? 0),
                'counted_amount' => bcadd((string) ($row->counted ?? '0'), '0.00', 2),
                'total_variance' => bcadd((string) ($row->abs_diff ?? '0'), '0.00', 2),
            ],
            'comparisons' => [],
            'series'      => [],
        ];
    }

    private function consolidado(int $b, CarbonImmutable $s, CarbonImmutable $e, CarbonImmutable $ps, CarbonImmutable $pe, ?int $branchId): array
    {
        return [
            'totals'      => [
                'ventas'     => $this->ventas($b, $s, $e, $ps, $pe, $branchId)['totals'],
                'cartera'    => $this->cartera($b)['totals'],
                'inventario' => $this->inventario($b, $s, $e)['totals'],
                'caja'       => $this->caja($b, $s, $e, $branchId)['totals'],
            ],
            'comparisons' => [],
            'series'      => [],
        ];
    }
}
