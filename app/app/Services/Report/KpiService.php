<?php

declare(strict_types=1);

namespace App\Services\Report;

use App\Enums\PeriodType;
use App\Exceptions\KpiRecalculationException;
use App\Models\KpiSnapshot;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * RF-12-02/03 · Recálculo de KPIs. Los períodos se resuelven en business.timezone y se consultan
 * como intervalos SEMIABIERTOS [inicio, fin) convertidos a UTC (los timestamps se almacenan en UTC).
 * NO se usan las vistas vw_kpi_* para el corte por período: agrupan por DATE() en UTC y no respetan
 * el corte local (CONVERT_TZ en vistas queda diferido a Fase 2); se consulta la fuente operativa con
 * límites UTC.
 *
 * ALCANCE (RF-12-03): por cada negocio y período se calcula el snapshot GLOBAL (branch_id NULL) y un
 * snapshot POR CADA SUCURSAL, de modo que ninguna meta por sucursal quede sin indicador asociado.
 * Cada cálculo por sucursal filtra estrictamente los datos de esa sucursal (KPI-02 vía bodega, KPI-04
 * vía usuarios de la sucursal, KPI-05/01/08 vía branch_id de la factura, KPI-03/07 vía branch_id).
 *
 * Todo el recálculo de un negocio/período (global + sucursales) es ATÓMICO (una transacción, sin
 * snapshots parciales) e idempotente (upsert sobre uniq_kpi_snapshot). KPI-06 (agregador) se calcula
 * AL FINAL por ámbito. Sin float: DECIMAL/BCMath.
 */
final class KpiService
{
    private const VALUE_SCALE = 4;

    /**
     * @return array{recalculated:bool, period:array{start:string,end:string}, scopes:int}
     */
    public function calcular(int $businessId, PeriodType $periodType, ?string $referenceDate = null): array
    {
        try {
            $p = $this->resolvePeriod($businessId, $periodType, $referenceDate);

            return DB::transaction(function () use ($businessId, $periodType, $p): array {
                // Ámbito global (branch_id NULL) + un ámbito por cada sucursal del negocio.
                $this->calcularEscope($businessId, $periodType, $p, null);

                $branchIds = DB::table('branches')->where('business_id', $businessId)->pluck('id');
                foreach ($branchIds as $branchId) {
                    $this->calcularEscope($businessId, $periodType, $p, (int) $branchId);
                }

                return [
                    'recalculated' => true,
                    'period'       => ['start' => $p['startDate'], 'end' => $p['endDate']],
                    'scopes'       => 1 + $branchIds->count(),
                ];
            });
        } catch (KpiRecalculationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            report($e);
            throw new KpiRecalculationException($periodType->value, $e); // ERR-12B (500).
        }
    }

    /** Calcula el juego completo de KPIs de un ÁMBITO (global si branchId es null; si no, esa sucursal). */
    private function calcularEscope(int $b, PeriodType $pt, array $p, ?int $branchId): void
    {
        $achievements = [];

        $this->kpi01($b, $pt, $p, $branchId);
        $this->kpi02($b, $pt, $p, $branchId);
        $achievements[] = $this->kpi03($b, $pt, $p, $branchId);
        $achievements[] = $this->kpi04($b, $pt, $p, $branchId);
        $achievements[] = $this->kpi05($b, $pt, $p, $branchId);
        $this->kpi07($b, $pt, $p, $branchId);
        $achievements[] = $this->kpi08($b, $pt, $p, $branchId);
        $achievements[] = $this->ticketPromedio($b, $pt, $p, $branchId);

        // KPI-06 agregador: promedio de logros VÁLIDOS de los KPIs con meta del ámbito (AL FINAL).
        $this->kpi06($b, $pt, $p, $branchId, array_values(array_filter($achievements, static fn ($a) => $a !== null)));
    }

    /**
     * Dashboard consolidado (RF-12-03): instantáneas GLOBALES del negocio para el período pedido.
     * Si no se indica period_start, se resuelve el período vigente en business.timezone (sin cálculos
     * de período en el controlador). Solo sirve códigos conocidos y datos del propio negocio.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, KpiSnapshot>
     */
    public function dashboard(int $businessId, PeriodType $periodType, ?string $periodStart = null): \Illuminate\Database\Eloquent\Collection
    {
        $start = $periodStart ?? $this->resolvePeriod($businessId, $periodType, null)['startDate'];

        return KpiSnapshot::query()->withoutGlobalScopes()
            ->where('business_id', $businessId)   // Aislamiento explícito (las vistas/agregados no se auto-protegen).
            ->whereNull('branch_id')              // Panel consolidado (global).
            ->where('period_type', $periodType->value)
            ->where('period_start', $start)
            ->orderBy('kpi_code')
            ->get();
    }

    // ---------------- KPIs (fuentes operativas, límites UTC, filtrado por ámbito) ----------------

    /**
     * KPI-01 · Correspondencia ventas–caja–inventario (índice de salud, sin meta).
     * FRD (KPI-01): "facturado contra la suma de cobros y cuentas por cobrar generadas en el periodo".
     * Fase 1 mide la integridad ventas↔cobro/CxC (contado cobrado + crédito con CxC) / facturado.
     * La correspondencia física de inventario la cubre KPI-02; la conciliación 3-way completa
     * inventario→retiro→cobro→caja es el reporte RF-11-02 (MOD-11). La pata de inventario NO forma
     * parte de la fuente implementada de KPI-01 (diferida a Fase 2); la metadata lo deja explícito.
     */
    private function kpi01(int $b, PeriodType $pt, array $p, ?int $branchId): ?string
    {
        $base = fn () => DB::table('invoices')->where('business_id', $b)
            ->where('status', 'emitida')
            ->where('issued_at', '>=', $p['utcStart'])->where('issued_at', '<', $p['utcEnd'])
            ->when($branchId !== null, fn (Builder $x) => $x->where('branch_id', $branchId));

        $invoiced = $this->n((string) (clone $base())->sum('total'));
        $contado  = $this->n((string) (clone $base())->where('payment_type', 'contado')->sum('total'));
        $credito  = $this->n((string) (clone $base())->where('payment_type', 'credito')
            ->whereIn('id', fn ($q) => $q->select('invoice_id')->from('accounts_receivables')->where('business_id', $b))
            ->sum('total'));

        $covered = bcadd($contado, $credito, self::VALUE_SCALE);
        $value   = bccomp($invoiced, '0', self::VALUE_SCALE) > 0
            ? $this->cap100(bcmul(bcdiv($covered, $invoiced, 6), '100', self::VALUE_SCALE))
            : '100.0000';

        $this->upsert($b, 'kpi_01', $pt, $p, $branchId, $value, null, [
            'measures'         => 'facturado_vs_cobros_y_cxc',
            'formula'          => '(contado_cobrado + credito_con_cxc) / facturado * 100',
            'invoiced'         => $invoiced,
            'covered'          => $covered,
            'contado'          => $contado,
            'credito'          => $credito,
            'inventory_leg'    => 'diferido_fase2 (correspondencia física en KPI-02; conciliación 3-way en RF-11-02)',
        ]);

        return null; // No goalable (índice de salud).
    }

    /** KPI-02 · Exactitud de stock (%) desde conteos físicos del período. Sucursal derivada por bodega. */
    private function kpi02(int $b, PeriodType $pt, array $p, ?int $branchId): ?string
    {
        $row = DB::table('physical_counts AS pc')
            ->when($branchId !== null, fn (Builder $x) => $x
                ->join('warehouses AS w', 'w.id', '=', 'pc.warehouse_id')
                ->where('w.branch_id', $branchId)) // KPI-02 deriva la sucursal mediante la bodega.
            ->where('pc.business_id', $b)
            ->where('pc.counted_at', '>=', $p['utcStart'])->where('pc.counted_at', '<', $p['utcEnd'])
            ->selectRaw('COALESCE(SUM(ABS(pc.difference)),0) abs_dev, COALESCE(SUM(pc.system_quantity),0) sys')
            ->first();

        $absDev = $this->n((string) ($row->abs_dev ?? '0'));
        $sys    = $this->n((string) ($row->sys ?? '0'));
        $value  = bccomp($sys, '0', self::VALUE_SCALE) > 0
            ? bcmul(bcsub('1', bcdiv($absDev, $sys, 6), 6), '100', self::VALUE_SCALE)
            : '100.0000';

        $this->upsert($b, 'kpi_02', $pt, $p, $branchId, $value, null, ['abs_deviation' => $absDev, 'system_total' => $sys]);

        return null;
    }

    /** KPI-03 · Faltantes NO justificados (monto): anomalías activas de faltante del período. Meta a la baja. */
    private function kpi03(int $b, PeriodType $pt, array $p, ?int $branchId): ?string
    {
        $row = DB::table('anomalies AS a')
            ->join('anomaly_rules AS r', 'r.id', '=', 'a.anomaly_rule_id')
            ->where('a.business_id', $b)
            ->where('r.code', 'faltante_inventario')
            ->whereIn('a.status', ['detectada', 'notificada', 'en_revision'])
            ->where('a.detected_at', '>=', $p['utcStart'])->where('a.detected_at', '<', $p['utcEnd'])
            ->when($branchId !== null, fn (Builder $x) => $x->where('a.branch_id', $branchId))
            ->selectRaw('COALESCE(SUM(ABS(a.difference)),0) shortage, COUNT(*) cnt')
            ->first();

        $value = $this->n((string) ($row->shortage ?? '0'));

        return $this->upsert($b, 'kpi_03', $pt, $p, $branchId, $value, $this->target($b, 'kpi_03', $pt, $p, $branchId),
            ['shortage_count' => (int) ($row->cnt ?? 0)]);
    }

    /** KPI-04 · Uso del sistema (% de personal activo con actividad sobre habilitado). Sucursal = usuarios de la sucursal. */
    private function kpi04(int $b, PeriodType $pt, array $p, ?int $branchId): ?string
    {
        $active = (int) DB::table('audit_logs AS al')
            ->when($branchId !== null, fn (Builder $x) => $x
                ->join('users AS u', 'u.id', '=', 'al.user_id')
                ->where('u.branch_id', $branchId)) // KPI-04 usa usuarios de la sucursal.
            ->where('al.business_id', $b)
            ->whereNotNull('al.user_id')
            ->where('al.created_at', '>=', $p['utcStart'])->where('al.created_at', '<', $p['utcEnd'])
            ->distinct()->count('al.user_id');

        $enabled = (int) DB::table('users')->where('business_id', $b)
            ->where('is_active', true)->whereNull('deleted_at')
            ->when($branchId !== null, fn (Builder $x) => $x->where('branch_id', $branchId))
            ->count();

        $value = $enabled > 0
            ? $this->cap100(bcmul(bcdiv((string) $active, (string) $enabled, 6), '100', self::VALUE_SCALE))
            : '0.0000';

        return $this->upsert($b, 'kpi_04', $pt, $p, $branchId, $value, $this->target($b, 'kpi_04', $pt, $p, $branchId),
            ['active_users' => $active, 'enabled_users' => $enabled]);
    }

    /** KPI-05 · Evolución de ventas (monto) del período. Sucursal = facturas de la sucursal. */
    private function kpi05(int $b, PeriodType $pt, array $p, ?int $branchId): ?string
    {
        [$sold, $count] = $this->ventasPeriodo($b, $p, $branchId);
        $meta = ['invoice_count' => $count, 'avg_ticket' => $count > 0 ? bcdiv($sold, (string) $count, self::VALUE_SCALE) : '0.0000'];

        return $this->upsert($b, 'kpi_05', $pt, $p, $branchId, $sold, $this->target($b, 'kpi_05', $pt, $p, $branchId), $meta);
    }

    /** ticket_promedio · derivado de ventas (monto) del período. */
    private function ticketPromedio(int $b, PeriodType $pt, array $p, ?int $branchId): ?string
    {
        [$sold, $count] = $this->ventasPeriodo($b, $p, $branchId);
        $value = $count > 0 ? bcdiv($sold, (string) $count, self::VALUE_SCALE) : '0.0000';

        return $this->upsert($b, 'ticket_promedio', $pt, $p, $branchId, $value, $this->target($b, 'ticket_promedio', $pt, $p, $branchId),
            ['invoice_count' => $count, 'total_sold' => $sold]);
    }

    /** KPI-07 · Disponibilidad de reportes (% de corridas programadas completadas) del período. */
    private function kpi07(int $b, PeriodType $pt, array $p, ?int $branchId): ?string
    {
        $row = DB::table('reconciliation_runs')->where('business_id', $b)
            ->where('run_type', 'programada')
            ->where('started_at', '>=', $p['utcStart'])->where('started_at', '<', $p['utcEnd'])
            ->when($branchId !== null, fn (Builder $x) => $x->where('branch_id', $branchId))
            ->selectRaw("COALESCE(SUM(CASE WHEN status = 'completada' THEN 1 ELSE 0 END),0) c, COUNT(*) t")
            ->first();

        $c = (int) ($row->c ?? 0);
        $t = (int) ($row->t ?? 0);
        $value = $t > 0 ? bcmul(bcdiv((string) $c, (string) $t, 6), '100', self::VALUE_SCALE) : '100.0000';

        $this->upsert($b, 'kpi_07', $pt, $p, $branchId, $value, null, ['completed' => $c, 'total' => $t]);

        return null;
    }

    /**
     * KPI-08 · Recuperación de cartera (%) con SEMÁNTICA HISTÓRICA REPRODUCIBLE (cohorte del período).
     * Contabiliza TODOS los eventos que afectan la cartera SIN doble conteo ni valores negativos:
     *   - emitida:            Σ invoice.total (obligación ORIGINAL, inmutable) de las facturas a crédito
     *                         de la cohorte VIGENTES al cierre (no anuladas antes del corte).
     *   - pagos_iniciales:    abonos registrados AL EMITIR (receivable_payments materializados 1:1 del
     *                         invoice_payment inicial; paid_at == issued_at a granularidad de segundo).
     *   - abonos_posteriores: abonos posteriores (receivable_payments con paid_at > issued_at).
     *   - recuperada:         pagos_iniciales + abonos_posteriores registrados ANTES del cierre.
     *   - reducciones_cxc:    resoluciones de nota de crédito 'reduccion_cxc' EMITIDAS antes del cierre
     *                         (bajan el pendiente pero NO son dinero recuperado).
     *   - pendiente:          Σ por CxC de GREATEST(emitida − recuperada − reducciones, 0) al cierre.
     *   - vencida:            porción pendiente de las CxC con due_date ya vencida (<= fin local).
     * Un abono, NC o anulación POSTERIOR al cierre no reescribe el período: se usan paid_at, cn.issued_at
     * y voided_at, nunca los acumulados vivos ar.paid_amount/ar.total_amount/ar.balance. Sucursal desde la factura.
     */
    private function kpi08(int $b, PeriodType $pt, array $p, ?int $branchId): ?string
    {
        $endDate = $p['endDate']; // AAAA-MM-DD interno (sin superficie de inyección).

        // Abonos por CxC hasta el cierre, separando inicial (paid_at == emisión) de posterior.
        $paid = DB::table('receivable_payments AS rp')
            ->join('accounts_receivables AS ar2', 'ar2.id', '=', 'rp.accounts_receivable_id')
            ->join('invoices AS i2', 'i2.id', '=', 'ar2.invoice_id')
            ->where('rp.business_id', $b)
            ->where('rp.paid_at', '<', $p['utcEnd'])
            ->groupBy('rp.accounts_receivable_id')
            ->selectRaw(
                'rp.accounts_receivable_id AS ar_id, '
                . 'SUM(rp.amount) AS recovered, '
                . 'SUM(CASE WHEN rp.paid_at <= i2.issued_at THEN rp.amount ELSE 0 END) AS initial'
            );

        // Reducciones de CxC por nota de crédito EMITIDA antes del cierre (por factura de la cohorte).
        $reduced = DB::table('credit_note_resolutions AS cnr')
            ->join('credit_notes AS cn', 'cn.id', '=', 'cnr.credit_note_id')
            ->where('cnr.business_id', $b)
            ->where('cnr.resolution_type', 'reduccion_cxc')
            ->where('cn.status', 'emitida')
            ->where('cn.issued_at', '<', $p['utcEnd'])
            ->groupBy('cn.invoice_id')
            ->selectRaw('cn.invoice_id AS invoice_id, SUM(cnr.amount) AS reduced');

        $row = DB::table('accounts_receivables AS ar')
            ->join('invoices AS i', 'i.id', '=', 'ar.invoice_id')
            ->leftJoinSub($paid, 'paid', 'paid.ar_id', '=', 'ar.id')
            ->leftJoinSub($reduced, 'red', 'red.invoice_id', '=', 'i.id')
            ->where('ar.business_id', $b)
            ->where('i.payment_type', 'credito')
            ->where('i.issued_at', '>=', $p['utcStart'])->where('i.issued_at', '<', $p['utcEnd'])
            // Vigente al cierre: no anulada, o anulada DESPUÉS del corte (no reescribe historia).
            ->where(fn (Builder $q) => $q->whereNull('i.voided_at')->orWhere('i.voided_at', '>=', $p['utcEnd']))
            ->when($branchId !== null, fn (Builder $x) => $x->where('i.branch_id', $branchId)) // sucursal desde la factura.
            ->selectRaw(
                'COALESCE(SUM(i.total),0) emitida, '
                . 'COALESCE(SUM(COALESCE(paid.recovered,0)),0) recuperada, '
                . 'COALESCE(SUM(COALESCE(paid.initial,0)),0) iniciales, '
                . 'COALESCE(SUM(COALESCE(red.reduced,0)),0) reducciones, '
                . 'COALESCE(SUM(GREATEST(i.total - COALESCE(paid.recovered,0) - COALESCE(red.reduced,0), 0)),0) pendiente, '
                . "COALESCE(SUM(CASE WHEN ar.due_date <= '{$endDate}' "
                . 'THEN GREATEST(i.total - COALESCE(paid.recovered,0) - COALESCE(red.reduced,0), 0) ELSE 0 END),0) vencida'
            )
            ->first();

        $emitida    = $this->n((string) ($row->emitida ?? '0'));
        $recuperada = $this->n((string) ($row->recuperada ?? '0'));
        $iniciales  = $this->n((string) ($row->iniciales ?? '0'));
        $value = bccomp($emitida, '0', self::VALUE_SCALE) > 0
            ? bcmul(bcdiv($recuperada, $emitida, 6), '100', self::VALUE_SCALE)
            : '0.0000';

        return $this->upsert($b, 'kpi_08', $pt, $p, $branchId, $value, $this->target($b, 'kpi_08', $pt, $p, $branchId), [
            'fecha_de_corte'     => $endDate,
            'emitida'            => $emitida,
            'pagos_iniciales'    => $iniciales,
            'abonos_posteriores' => bcsub($recuperada, $iniciales, self::VALUE_SCALE),
            'recuperada'         => $recuperada,
            'reducciones_cxc'    => $this->n((string) ($row->reducciones ?? '0')),
            'pendiente'          => $this->n((string) ($row->pendiente ?? '0')),
            'vencida'            => $this->n((string) ($row->vencida ?? '0')),
        ]);
    }

    /** KPI-06 · Agregador: promedio de los logros de los KPIs con meta del ámbito (calculado AL FINAL). */
    private function kpi06(int $b, PeriodType $pt, array $p, ?int $branchId, array $achievements): void
    {
        if ($achievements === []) {
            $this->upsert($b, 'kpi_06', $pt, $p, $branchId, '0.0000', null, ['sample' => 0]);
            return;
        }

        $sum = array_reduce($achievements, static fn (string $c, string $a): string => bcadd($c, $a, self::VALUE_SCALE), '0.0000');
        $avg = bcdiv($sum, (string) count($achievements), self::VALUE_SCALE);

        $this->upsert($b, 'kpi_06', $pt, $p, $branchId, $avg, null, ['sample' => count($achievements)]);
    }

    /** @return array{0:string,1:int} [total_sold, invoice_count] del período (ámbito). */
    private function ventasPeriodo(int $b, array $p, ?int $branchId): array
    {
        $row = DB::table('invoices')->where('business_id', $b)
            ->where('status', 'emitida')
            ->where('issued_at', '>=', $p['utcStart'])->where('issued_at', '<', $p['utcEnd'])
            ->when($branchId !== null, fn (Builder $x) => $x->where('branch_id', $branchId))
            ->selectRaw('COALESCE(SUM(total),0) sold, COUNT(*) cnt')
            ->first();

        return [$this->n((string) ($row->sold ?? '0')), (int) ($row->cnt ?? 0)];
    }

    // ---------------- Persistencia idempotente (upsert atómico) ----------------

    /** Upsert de la instantánea vía ON DUPLICATE KEY (uniq_kpi_snapshot); devuelve el % de logro. */
    private function upsert(int $b, string $code, PeriodType $pt, array $p, ?int $branchId, string $value, ?string $target, ?array $meta): ?string
    {
        $achievement = $this->achievement($code, $value, $target);
        $now = now();

        KpiSnapshot::query()->withoutGlobalScopes()->upsert(
            [[
                'business_id'     => $b,
                'branch_id'       => $branchId,     // NULL = global; si no, la sucursal del ámbito.
                'kpi_code'        => $code,
                'period_type'     => $pt->value,
                'period_start'    => $p['startDate'],
                'period_end'      => $p['endDate'],
                'value'           => $value,
                'target_value'    => $target,       // Meta CONGELADA en el snapshot.
                'achievement_pct' => $achievement,
                'metadata'        => json_encode($meta, JSON_UNESCAPED_UNICODE),
                'calculated_at'   => $now,
                'created_at'      => $now,
            ]],
            ['business_id', 'branch_key', 'kpi_code', 'period_type', 'period_start'],
            ['period_end', 'value', 'target_value', 'achievement_pct', 'metadata', 'calculated_at'],
        );

        return $achievement;
    }

    /**
     * Meta VIVA del ámbito para el período (global si branchId es null; si no, la de esa sucursal).
     * Una meta de sucursal aplica SOLO a su snapshot; una meta global no contamina snapshots de sucursal.
     * Se congela al persistir el snapshot.
     */
    private function target(int $b, string $code, PeriodType $pt, array $p, ?int $branchId): ?string
    {
        $goal = DB::table('business_goals')->where('business_id', $b)
            ->when($branchId === null,
                fn (Builder $q) => $q->whereNull('branch_id'),
                fn (Builder $q) => $q->where('branch_id', $branchId))
            ->where('kpi_code', $code)
            ->where('period_type', $pt->value)
            ->where('period_start', $p['startDate'])
            ->first();

        return $goal !== null ? (string) $goal->target_value : null;
    }

    /** % de logro respetando la dirección del registro canónico ('down' = menos es mejor). Nunca divide por cero. */
    private function achievement(string $code, string $value, ?string $target): ?string
    {
        if ($target === null || bccomp($target, '0', 4) <= 0) {
            return null;
        }

        if ((string) config("kpis.$code.direction", 'up') === 'down') {
            // Menos es mejor: sin desviación ⇒ 100 %; si no, target/valor.
            return bccomp($value, '0', 4) <= 0
                ? '100.00'
                : bcmul(bcdiv($target, $value, 6), '100', 2);
        }

        return bcmul(bcdiv($value, $target, 6), '100', 2);
    }

    /**
     * Resuelve el período en business.timezone y expone los límites UTC semiabiertos [inicio, fin)
     * para consultar timestamps UTC, más las fechas locales de inicio/fin (inclusivas) del snapshot.
     *
     * @return array{startDate:string,endDate:string,utcStart:CarbonImmutable,utcEnd:CarbonImmutable}
     */
    private function resolvePeriod(int $b, PeriodType $pt, ?string $ref): array
    {
        $tz   = (string) (DB::table('businesses')->where('id', $b)->value('timezone') ?: config('app.timezone'));
        $date = $ref !== null ? CarbonImmutable::parse($ref, $tz) : CarbonImmutable::now($tz);

        [$localStart, $localEndExclusive] = match ($pt) {
            PeriodType::Diario  => [$date->startOfDay(), $date->startOfDay()->addDay()],
            PeriodType::Semanal => [$date->startOfWeek(), $date->startOfWeek()->addWeek()],
            PeriodType::Mensual => [$date->startOfMonth(), $date->startOfMonth()->addMonth()],
            PeriodType::Anual   => [$date->startOfYear(), $date->startOfYear()->addYear()],
        };

        return [
            'startDate' => $localStart->toDateString(),
            'endDate'   => $localEndExclusive->subDay()->toDateString(), // Fin inclusivo del período local.
            'utcStart'  => $localStart->utc(),
            'utcEnd'    => $localEndExclusive->utc(),                    // Límite superior EXCLUSIVO.
        ];
    }

    private function n(string $v): string
    {
        return bcadd($v === '' ? '0' : $v, '0', self::VALUE_SCALE);
    }

    private function cap100(string $v): string
    {
        return bccomp($v, '100', self::VALUE_SCALE) > 0 ? '100.0000' : $v;
    }
}
