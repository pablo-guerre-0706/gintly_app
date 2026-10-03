<?php

declare(strict_types=1);

namespace App\Services\Report;

use App\Enums\ReportType;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * RF-12-01 · Reportería consolidada, comparable y trazable (SOLO LECTURA).
 * Los rangos se interpretan en business.timezone y se consultan como intervalos SEMIABIERTOS
 * [inicio, fin) convertidos a UTC (los timestamps se almacenan en UTC), igual que KpiService.
 * NO se consultan snapshots como fuente operativa; se consultan las tablas fuente. Cada consulta
 * filtra business_id explícitamente (las vistas/tablas no están protegidas por BusinessScope aquí).
 * Cada reporte devuelve totales + período actual + período equivalente anterior + variación + serie.
 * Sin float: DECIMAL/BCMath, decimales como cadenas con escala estable. Períodos vacíos → ceros.
 */
final class ReportService
{
    /**
     * @return array{type:string, period:array, totals:array, comparisons:array, series:array, metadata:array}
     */
    public function generar(int $businessId, ReportType $type, ?string $from, ?string $to, ?int $branchId): array
    {
        $p = $this->resolvePeriod($businessId, $from, $to);

        $payload = match ($type) {
            ReportType::Ventas      => $this->ventas($businessId, $p, $branchId),
            ReportType::Cartera     => $this->cartera($businessId, $p, $branchId),
            ReportType::Inventario  => $this->inventario($businessId, $p, $branchId),
            ReportType::Caja        => $this->caja($businessId, $p, $branchId),
            ReportType::Consolidado => $this->consolidado($businessId, $p, $branchId),
        };

        return array_merge(
            [
                'type'   => $type->value,
                'period' => ['from' => $p['startDate'], 'to' => $p['endDate']],
            ],
            $payload,
            ['metadata' => [
                'timezone'          => $p['tz'],
                'previous_period'   => ['from' => $p['prevStartDate'], 'to' => $p['prevEndDate']],
                'utc_bounds'        => [$p['utcStart']->toIso8601String(), $p['utcEnd']->toIso8601String()],
                'branch_id'         => $branchId,
                'generated_at'      => CarbonImmutable::now()->toIso8601String(),
            ]],
        );
    }

    // ---------------- Reportes (fuentes operativas, límites UTC) ----------------

    private function ventas(int $b, array $p, ?int $branchId): array
    {
        $sales = fn (CarbonImmutable $s, CarbonImmutable $e): object => DB::table('invoices')
            ->where('business_id', $b)->where('status', 'emitida')
            ->where('issued_at', '>=', $s)->where('issued_at', '<', $e)
            ->when($branchId !== null, fn (Builder $x) => $x->where('branch_id', $branchId))
            ->selectRaw('COALESCE(SUM(total),0) sold, COUNT(*) cnt')->first();

        $cur  = $sales($p['utcStart'], $p['utcEnd']);
        $prev = $sales($p['prevUtcStart'], $p['prevUtcEnd']);

        $current  = $this->d((string) ($cur->sold ?? '0'));
        $previous = $this->d((string) ($prev->sold ?? '0'));
        $count    = (int) ($cur->cnt ?? 0);

        $series = DB::table('invoices')->where('business_id', $b)->where('status', 'emitida')
            ->where('issued_at', '>=', $p['utcStart'])->where('issued_at', '<', $p['utcEnd'])
            ->when($branchId !== null, fn (Builder $x) => $x->where('branch_id', $branchId))
            ->selectRaw("DATE(CONVERT_TZ(issued_at, '+00:00', ?)) day, COALESCE(SUM(total),0) sold, COUNT(*) cnt", [$p['offset']])
            ->groupBy('day')->orderBy('day')->get()
            ->map(fn ($r) => ['day' => $r->day, 'total_sold' => $this->d((string) $r->sold), 'invoice_count' => (int) $r->cnt])
            ->all();

        return [
            'totals'      => [
                'total_sold'    => $current,
                'invoice_count' => $count,
                'avg_ticket'    => $count > 0 ? bcdiv($current, (string) $count, 2) : '0.00',
            ],
            'comparisons' => $this->comparison($current, $previous),
            'series'      => $series,
        ];
    }

    /** Cartera del período: cohorte de CxC de facturas a crédito emitidas en el rango (comparable por período). */
    private function cartera(int $b, array $p, ?int $branchId): array
    {
        $cohort = fn (CarbonImmutable $s, CarbonImmutable $e): object => DB::table('accounts_receivables AS ar')
            ->join('invoices AS i', 'i.id', '=', 'ar.invoice_id')
            ->where('ar.business_id', $b)
            ->where('i.payment_type', 'credito')->where('i.status', 'emitida')
            ->where('i.issued_at', '>=', $s)->where('i.issued_at', '<', $e)
            ->when($branchId !== null, fn (Builder $x) => $x->where('i.branch_id', $branchId))
            ->selectRaw('COALESCE(SUM(ar.total_amount),0) emitida, COALESCE(SUM(ar.paid_amount),0) recuperada, '
                . 'COALESCE(SUM(ar.balance),0) pendiente, '
                . "COALESCE(SUM(CASE WHEN ar.status='vencida' THEN ar.balance ELSE 0 END),0) vencida")
            ->first();

        $cur  = $cohort($p['utcStart'], $p['utcEnd']);
        $prev = $cohort($p['prevUtcStart'], $p['prevUtcEnd']);

        return [
            'totals'      => [
                'emitida'    => $this->d((string) ($cur->emitida ?? '0')),
                'recuperada' => $this->d((string) ($cur->recuperada ?? '0')),
                'pendiente'  => $this->d((string) ($cur->pendiente ?? '0')),
                'vencida'    => $this->d((string) ($cur->vencida ?? '0')),
            ],
            'comparisons' => $this->comparison(
                $this->d((string) ($cur->recuperada ?? '0')),
                $this->d((string) ($prev->recuperada ?? '0')),
            ),
            'series'      => [],
        ];
    }

    private function inventario(int $b, array $p, ?int $branchId): array
    {
        $exact = DB::table('physical_counts')->where('business_id', $b)
            ->where('counted_at', '>=', $p['utcStart'])->where('counted_at', '<', $p['utcEnd'])
            ->selectRaw('COALESCE(SUM(ABS(difference)),0) abs_dev, COALESCE(SUM(system_quantity),0) sys')->first();

        $shortage = fn (CarbonImmutable $s, CarbonImmutable $e): string => (string) DB::table('anomalies AS a')
            ->join('anomaly_rules AS r', 'r.id', '=', 'a.anomaly_rule_id')
            ->where('a.business_id', $b)->where('r.code', 'faltante_inventario')
            ->whereIn('a.status', ['detectada', 'notificada', 'en_revision'])
            ->where('a.detected_at', '>=', $s)->where('a.detected_at', '<', $e)
            ->when($branchId !== null, fn (Builder $x) => $x->where('a.branch_id', $branchId))
            ->sum(DB::raw('ABS(a.difference)'));

        $absDev = $this->d3((string) ($exact->abs_dev ?? '0'));
        $sys    = $this->d3((string) ($exact->sys ?? '0'));
        $curSh  = $this->d($shortage($p['utcStart'], $p['utcEnd']));
        $prevSh = $this->d($shortage($p['prevUtcStart'], $p['prevUtcEnd']));

        return [
            'totals'      => [
                'abs_deviation'        => $absDev,
                'exactitud_pct'        => bccomp($sys, '0', 3) > 0 ? bcmul(bcsub('1', bcdiv($absDev, $sys, 6), 6), '100', 2) : '100.00',
                'unjustified_shortage' => $curSh,
            ],
            'comparisons' => $this->comparison($curSh, $prevSh),
            'series'      => [],
        ];
    }

    private function caja(int $b, array $p, ?int $branchId): array
    {
        $sessions = fn (CarbonImmutable $s, CarbonImmutable $e): object => DB::table('cash_sessions')
            ->where('business_id', $b)->where('status', 'cerrada')
            ->where('created_at', '>=', $s)->where('created_at', '<', $e)
            ->when($branchId !== null, fn (Builder $x) => $x->where('branch_id', $branchId))
            ->selectRaw('COUNT(*) sessions, COALESCE(SUM(ABS(difference)),0) abs_diff, COALESCE(SUM(counted_amount),0) counted')
            ->first();

        $cur  = $sessions($p['utcStart'], $p['utcEnd']);
        $prev = $sessions($p['prevUtcStart'], $p['prevUtcEnd']);

        return [
            'totals'      => [
                'sessions'       => (int) ($cur->sessions ?? 0),
                'counted_amount' => $this->d((string) ($cur->counted ?? '0')),
                'total_variance' => $this->d((string) ($cur->abs_diff ?? '0')),
            ],
            'comparisons' => $this->comparison(
                $this->d((string) ($cur->counted ?? '0')),
                $this->d((string) ($prev->counted ?? '0')),
            ),
            'series'      => [],
        ];
    }

    private function consolidado(int $b, array $p, ?int $branchId): array
    {
        return [
            'totals'      => [
                'ventas'     => $this->ventas($b, $p, $branchId)['totals'],
                'cartera'    => $this->cartera($b, $p, $branchId)['totals'],
                'inventario' => $this->inventario($b, $p, $branchId)['totals'],
                'caja'       => $this->caja($b, $p, $branchId)['totals'],
            ],
            'comparisons' => [],
            'series'      => [],
        ];
    }

    // ---------------- Helpers ----------------

    /** @return array{previous_total:string, delta:string, variation_pct:string} */
    private function comparison(string $current, string $previous): array
    {
        $delta = bcsub($current, $previous, 2);
        $variation = bccomp($previous, '0', 2) > 0
            ? bcmul(bcdiv($delta, $previous, 6), '100', 2)
            : '0.00';

        return ['previous_total' => $previous, 'delta' => $delta, 'variation_pct' => $variation];
    }

    /**
     * Resuelve el período en business.timezone: fechas locales inclusivas + límites UTC semiabiertos
     * del período actual y del período EQUIVALENTE anterior (mismo span, no solapado).
     */
    private function resolvePeriod(int $b, ?string $from, ?string $to): array
    {
        $tz = (string) (DB::table('businesses')->where('id', $b)->value('timezone') ?: config('app.timezone'));

        $end   = $to !== null ? CarbonImmutable::parse($to, $tz) : CarbonImmutable::now($tz);
        $start = $from !== null ? CarbonImmutable::parse($from, $tz) : $end->startOfMonth();

        $localStart        = $start->startOfDay();
        $localEndExclusive = $end->startOfDay()->addDay(); // 'to' inclusivo.
        $spanDays          = (int) $localStart->diffInDays($localEndExclusive);

        $prevEndExclusive = $localStart;
        $prevStart        = $localStart->subDays($spanDays);

        return [
            'tz'            => $tz,
            'offset'        => $localStart->format('P'), // p. ej. -06:00 (zonas de offset fijo).
            'startDate'     => $localStart->toDateString(),
            'endDate'       => $localEndExclusive->subDay()->toDateString(),
            'prevStartDate' => $prevStart->toDateString(),
            'prevEndDate'   => $prevEndExclusive->subDay()->toDateString(),
            'utcStart'      => $localStart->utc(),
            'utcEnd'        => $localEndExclusive->utc(),
            'prevUtcStart'  => $prevStart->utc(),
            'prevUtcEnd'    => $prevEndExclusive->utc(),
        ];
    }

    private function d(string $v): string
    {
        return bcadd($v === '' ? '0' : $v, '0', 2);
    }

    private function d3(string $v): string
    {
        return bcadd($v === '' ? '0' : $v, '0', 3);
    }
}
