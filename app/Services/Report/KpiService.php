<?php

declare(strict_types=1);

namespace App\Services\Report;

use App\Enums\PeriodType;
use App\Exceptions\KpiRecalculationException;
use App\Models\KpiSnapshot;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final class KpiService
{
    /**
     * Orden de dependencias: KPI-01..05,07,08 y ticket_promedio → KPI-06 (agregador) al final.
     * @return array{recalculated:bool, period:array{start:string,end:string}}
     */
    public function calcular(int $businessId, PeriodType $periodType, ?string $referenceDate = null): array
    {
        try {
            [$start, $end] = $this->resolvePeriod($businessId, $periodType, $referenceDate);

            $achievements = [];

            $this->kpi01($businessId, $periodType, $start, $end);
            $this->kpi02($businessId, $periodType, $start, $end);
            $achievements[] = $this->kpi03($businessId, $periodType, $start, $end);
            $achievements[] = $this->kpi04($businessId, $periodType, $start, $end);
            $achievements[] = $this->kpi05($businessId, $periodType, $start, $end);
            $this->kpi07($businessId, $periodType, $start, $end);
            $achievements[] = $this->kpi08($businessId, $periodType, $start, $end);
            $achievements[] = $this->ticketPromedio($businessId, $periodType, $start, $end);

            // KPI-06 agregador: promedio de logros de los KPIs con meta (se calcula AL FINAL).
            $this->kpi06($businessId, $periodType, $start, $end, array_values(array_filter($achievements)));

            return [
                'recalculated' => true,
                'period'       => ['start' => $start->toDateString(), 'end' => $end->toDateString()],
            ];
        } catch (\Throwable $e) {
            report($e);
            throw new KpiRecalculationException($periodType->value, $e); // ERR-12B (500).
        }
    }

    // ---------------- KPIs ----------------

    /** KPI-01 · Correspondencia ventas-caja-inventario (cálculo directo). */
    private function kpi01(int $b, PeriodType $pt, CarbonImmutable $s, CarbonImmutable $e): ?string
    {
        $invoiced = (string) DB::table('invoices')->where('business_id', $b)
            ->where('status', 'emitida')
            ->whereBetween('issued_at', [$s->startOfDay(), $e->endOfDay()])
            ->sum('total');

        // Cada factura emitida debe estar "cubierta": contado (pagada) o crédito (con CxC).
        $contado = (string) DB::table('invoices')->where('business_id', $b)
            ->where('status', 'emitida')->where('payment_type', 'contado')
            ->whereBetween('issued_at', [$s->startOfDay(), $e->endOfDay()])->sum('total');

        $credito = (string) DB::table('invoices')->where('business_id', $b)
            ->where('status', 'emitida')->where('payment_type', 'credito')
            ->whereBetween('issued_at', [$s->startOfDay(), $e->endOfDay()])
            ->whereIn('id', fn ($q) => $q->select('invoice_id')->from('accounts_receivables')->where('business_id', $b))
            ->sum('total');

        $covered = bcadd($this->n($contado), $this->n($credito), 4);
        $value   = bccomp($this->n($invoiced), '0', 4) > 0
            ? $this->cap100(bcmul(bcdiv($covered, $this->n($invoiced), 6), '100', 4))
            : '100.0000';

        $this->upsert($b, 'kpi_01', $pt, $s, $e, $value, null, ['invoiced' => $this->n($invoiced), 'covered' => $covered]);

        return null; // No goalable.
    }

    /** KPI-02 · Exactitud de stock (%). */
    private function kpi02(int $b, PeriodType $pt, CarbonImmutable $s, CarbonImmutable $e): ?string
    {
        $row = DB::table('vw_kpi_exactitud_stock')->where('business_id', $b)
            ->whereBetween('day', [$s->toDateString(), $e->toDateString()])
            ->selectRaw('COALESCE(SUM(abs_deviation),0) abs_dev, COALESCE(SUM(system_total),0) sys_total')
            ->first();

        $absDev = $this->n((string) ($row->abs_dev ?? '0'));
        $sys    = $this->n((string) ($row->sys_total ?? '0'));
        $value  = bccomp($sys, '0', 4) > 0
            ? bcmul(bcsub('1', bcdiv($absDev, $sys, 6), 6), '100', 4)
            : '100.0000';

        $this->upsert($b, 'kpi_02', $pt, $s, $e, $value, null, ['abs_deviation' => $absDev, 'system_total' => $sys]);

        return null;
    }

    /** KPI-03 · Faltantes no justificados (monto). Meta a la baja. */
    private function kpi03(int $b, PeriodType $pt, CarbonImmutable $s, CarbonImmutable $e): ?string
    {
        $row = DB::table('vw_kpi_faltantes')->where('business_id', $b)
            ->whereBetween('day', [$s->toDateString(), $e->toDateString()])
            ->selectRaw('COALESCE(SUM(unjustified_shortage),0) shortage, COALESCE(SUM(shortage_count),0) cnt')
            ->first();

        $value = $this->n((string) ($row->shortage ?? '0'));

        return $this->upsert($b, 'kpi_03', $pt, $s, $e, $value, $this->target($b, 'kpi_03', $pt, $s),
            ['shortage_count' => (int) ($row->cnt ?? 0)]);
    }

    /** KPI-04 · Uso del sistema (% de personal activo sobre habilitado). */
    private function kpi04(int $b, PeriodType $pt, CarbonImmutable $s, CarbonImmutable $e): ?string
    {
        $active = (int) DB::table('vw_kpi_uso_sistema')->where('business_id', $b)
            ->whereBetween('day', [$s->toDateString(), $e->toDateString()])
            ->distinct()->count('user_id');

        $enabled = (int) DB::table('users')->where('business_id', $b)->count(); // Personal habilitado del negocio.

        $value = $enabled > 0
            ? bcmul(bcdiv((string) $active, (string) $enabled, 6), '100', 4)
            : '0.0000';

        return $this->upsert($b, 'kpi_04', $pt, $s, $e, $value, $this->target($b, 'kpi_04', $pt, $s),
            ['active_users' => $active, 'enabled_users' => $enabled]);
    }

    /** KPI-05 · Evolución de ventas (monto). */
    private function kpi05(int $b, PeriodType $pt, CarbonImmutable $s, CarbonImmutable $e): ?string
    {
        $row = DB::table('vw_kpi_ventas')->where('business_id', $b)
            ->whereBetween('day', [$s->toDateString(), $e->toDateString()])
            ->selectRaw('COALESCE(SUM(total_sold),0) sold, COALESCE(SUM(invoice_count),0) cnt')
            ->first();

        $sold  = $this->n((string) ($row->sold ?? '0'));
        $count = (int) ($row->cnt ?? 0);
        $meta  = ['invoice_count' => $count, 'avg_ticket' => $count > 0 ? bcdiv($sold, (string) $count, 4) : '0.0000'];

        return $this->upsert($b, 'kpi_05', $pt, $s, $e, $sold, $this->target($b, 'kpi_05', $pt, $s), $meta);
    }

    /** ticket_promedio · derivado de ventas (monto). */
    private function ticketPromedio(int $b, PeriodType $pt, CarbonImmutable $s, CarbonImmutable $e): ?string
    {
        $row = DB::table('vw_kpi_ventas')->where('business_id', $b)
            ->whereBetween('day', [$s->toDateString(), $e->toDateString()])
            ->selectRaw('COALESCE(SUM(total_sold),0) sold, COALESCE(SUM(invoice_count),0) cnt')
            ->first();

        $sold  = $this->n((string) ($row->sold ?? '0'));
        $count = (int) ($row->cnt ?? 0);
        $value = $count > 0 ? bcdiv($sold, (string) $count, 4) : '0.0000';

        return $this->upsert($b, 'ticket_promedio', $pt, $s, $e, $value, $this->target($b, 'ticket_promedio', $pt, $s),
            ['invoice_count' => $count]);
    }

    /** KPI-07 · Disponibilidad de reportes (% de corridas completadas). */
    private function kpi07(int $b, PeriodType $pt, CarbonImmutable $s, CarbonImmutable $e): ?string
    {
        $row = DB::table('vw_kpi_disponibilidad')->where('business_id', $b)
            ->whereBetween('day', [$s->toDateString(), $e->toDateString()])
            ->selectRaw('COALESCE(SUM(completed),0) c, COALESCE(SUM(total),0) t')
            ->first();

        $c = (string) ($row->c ?? '0');
        $t = (string) ($row->t ?? '0');
        $value = bccomp($this->n($t), '0', 4) > 0
            ? bcmul(bcdiv($this->n($c), $this->n($t), 6), '100', 4)
            : '100.0000';

        $this->upsert($b, 'kpi_07', $pt, $s, $e, $value, null, ['completed' => (int) $c, 'total' => (int) $t]);

        return null;
    }

    /** KPI-08 · Recuperación de cartera (%). */
    private function kpi08(int $b, PeriodType $pt, CarbonImmutable $s, CarbonImmutable $e): ?string
    {
        $row = DB::table('vw_kpi_cartera')->where('business_id', $b)->first();

        $emitida    = $this->n((string) ($row->emitida ?? '0'));
        $recuperada = $this->n((string) ($row->recuperada ?? '0'));
        $value = bccomp($emitida, '0', 4) > 0
            ? bcmul(bcdiv($recuperada, $emitida, 6), '100', 4)
            : '0.0000';

        return $this->upsert($b, 'kpi_08', $pt, $s, $e, $value, $this->target($b, 'kpi_08', $pt, $s), [
            'emitida'    => $emitida,
            'recuperada' => $recuperada,
            'pendiente'  => $this->n((string) ($row->pendiente ?? '0')),
            'vencida'    => $this->n((string) ($row->vencida ?? '0')),
        ]);
    }

    /** KPI-06 · Agregador: promedio de los logros de los KPIs con meta. */
    private function kpi06(int $b, PeriodType $pt, CarbonImmutable $s, CarbonImmutable $e, array $achievements): void
    {
        if ($achievements === []) {
            $this->upsert($b, 'kpi_06', $pt, $s, $e, '0.0000', null, ['sample' => 0]);
            return;
        }

        $sum = array_reduce($achievements, static fn (string $c, string $a): string => bcadd($c, $a, 4), '0.0000');
        $avg = bcdiv($sum, (string) count($achievements), 4);

        $this->upsert($b, 'kpi_06', $pt, $s, $e, $avg, null, ['sample' => count($achievements)]);
    }

    // ---------------- Persistencia idempotente ----------------

    /** Upsert de la instantánea; devuelve el % de logro (o null). */
    private function upsert(int $b, string $code, PeriodType $pt, CarbonImmutable $s, CarbonImmutable $e, string $value, ?string $target, ?array $meta): ?string
    {
        $achievement = $this->achievement($code, $value, $target);

        KpiSnapshot::withoutGlobalScopes()->updateOrCreate(
            [
                'business_id'  => $b,
                'branch_id'    => null,       // Fase 1: instantánea consolidada (branch-level → Fase 2).
                'kpi_code'     => $code,
                'period_type'  => $pt->value,
                'period_start' => $s->toDateString(),
            ],
            [
                'period_end'      => $e->toDateString(),
                'value'           => $value,
                'target_value'    => $target,
                'achievement_pct' => $achievement,
                'metadata'        => $meta,
                'calculated_at'   => now(),
            ],
        );

        return $achievement;
    }

    private function target(int $b, string $code, PeriodType $pt, CarbonImmutable $s): ?string
    {
        $goal = DB::table('business_goals')->where('business_id', $b)
            ->whereNull('branch_id') // Fase 1: metas globales.
            ->where('kpi_code', $code)
            ->where('period_type', $pt->value)
            ->where('period_start', $s->toDateString())
            ->first();

        return $goal !== null ? (string) $goal->target_value : null;
    }

    private function achievement(string $code, string $value, ?string $target): ?string
    {
        if ($target === null || bccomp($target, '0', 4) <= 0) {
            return null;
        }

        // Dirección del registro canónico: 'down' (menos es mejor) invierte el cociente.
        if ((string) config("kpis.$code.direction", 'up') === 'down') {
            return bccomp($value, '0', 4) <= 0
                ? '100.00'
                : bcmul(bcdiv($target, $value, 6), '100', 2);
        }

        return bcmul(bcdiv($value, $target, 6), '100', 2);
    }

    /** @return array{0:CarbonImmutable,1:CarbonImmutable} */
    private function resolvePeriod(int $b, PeriodType $pt, ?string $ref): array
    {
        $tz   = (string) (DB::table('businesses')->where('id', $b)->value('timezone') ?? config('app.timezone'));
        $date = $ref !== null ? CarbonImmutable::parse($ref, $tz) : CarbonImmutable::now($tz);

        return match ($pt) {
            PeriodType::Diario  => [$date->startOfDay(), $date->endOfDay()],
            PeriodType::Semanal => [$date->startOfWeek(), $date->endOfWeek()],
            PeriodType::Mensual => [$date->startOfMonth(), $date->endOfMonth()],
            PeriodType::Anual   => [$date->startOfYear(), $date->endOfYear()],
        };
    }

    private function n(string $v): string
    {
        return bcadd($v === '' ? '0' : $v, '0', 4);
    }

    private function cap100(string $v): string
    {
        return bccomp($v, '100', 4) > 0 ? '100.0000' : $v;
    }
}
