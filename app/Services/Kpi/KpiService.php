<?php

namespace App\Services\Kpi;

use App\Enums\KpiPeriodType;
use App\Models\Business;
use App\Models\KpiSnapshot;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class KpiService
{
    /**
     * Calcula TODOS los KPIs de un negocio para un período. Orden del .md:
     *   Fase 1 → KPI-01..05, 07, 08 (una transacción).
     *   Fase 2 → KPI-06 (lee los snapshots recién escritos).
     * business_id se filtra en CADA consulta a vista (ERR-12). Idempotente (updateOrCreate).
     */
    public function calcular(int $businessId, KpiPeriodType $type, ?CarbonInterface $ref = null): void
    {
        $business = Business::query()->findOrFail($businessId);
        $ref ??= Carbon::now($business->timezone);
        [$start, $end] = $this->periodBounds($business, $type, $ref);

        DB::transaction(function () use ($businessId, $type, $start, $end) {
            $this->calcVentas($businessId, $type, $start, $end);           // KPI-05
            $this->calcCartera($businessId, $type, $start, $end);          // KPI-08
            $this->calcExactitudStock($businessId, $type, $start, $end);   // KPI-02
            $this->calcFaltantes($businessId, $type, $start, $end);        // KPI-03
            $this->calcUsoSistema($businessId, $type, $start, $end);       // KPI-04
            $this->calcDisponibilidad($businessId, $type, $start, $end);   // KPI-07
            $this->calcCorrespondencia($businessId, $type, $start, $end);  // KPI-01 (sin vista)
        });

        // KPI-06 leyendo los snapshots ya escritos (segunda fase, fuera de la tx anterior).
        $this->calcCumplimientoMetas($businessId, $type, $start, $end);
    }

    // ─────────────────────── KPIs con vista (business_id SIEMPRE filtrado) ───────────────────────

    private function calcVentas(int $b, KpiPeriodType $t, $start, $end): void
    {
        $row = DB::table('vw_kpi_ventas')
            ->where('business_id', $b)   // ERR-12: filtro de tenant obligatorio
            ->whereBetween('dia', [$start->toDateString(), $end->toDateString()])
            ->selectRaw('COALESCE(SUM(ventas_total),0) total, COALESCE(SUM(num_facturas),0) facturas')
            ->first();

        $total    = (string) ($row->total ?? '0');
        $facturas = (int) ($row->facturas ?? 0);
        $ticket   = $facturas > 0 ? bcdiv($total, (string) $facturas, 2) : '0.00';

        $this->upsert($b, null, 'kpi_05', $t, $start, $end, $total,
            $this->goalFor($b, 'kpi_05', $t, $start),
            ['ticket_promedio' => $ticket, 'num_facturas' => $facturas]);
    }

    private function calcCartera(int $b, KpiPeriodType $t, $start, $end): void
    {
        $row = DB::table('vw_kpi_cartera')->where('business_id', $b)
            ->selectRaw('COALESCE(SUM(cartera_emitida),0) emitida, COALESCE(SUM(cartera_recuperada),0) recuperada, COALESCE(SUM(cartera_vencida),0) vencida')->first();

        $emitida = (string) ($row->emitida ?? '0');
        $pct = bccomp($emitida, '0', 2) > 0 ? bcmul(bcdiv((string) $row->recuperada, $emitida, 6), '100', 2) : '0.00';

        $this->upsert($b, null, 'kpi_08', $t, $start, $end, $pct,
            $this->goalFor($b, 'kpi_08', $t, $start),
            ['cartera_recuperada' => (string) $row->recuperada, 'cartera_vencida' => (string) $row->vencida]);
    }

    private function calcExactitudStock(int $b, KpiPeriodType $t, $start, $end): void
    {
        $row = DB::table('vw_kpi_exactitud_stock')->where('business_id', $b)
            ->whereBetween('dia', [$start->toDateString(), $end->toDateString()])
            ->selectRaw('COALESCE(SUM(desviacion_absoluta),0) desv, COALESCE(SUM(stock_sistema),0) sis')->first();

        $sis = (string) ($row->sis ?? '0');
        $pct = bccomp($sis, '0', 4) > 0
            ? bcmul(bcsub('1', bcdiv((string) $row->desv, $sis, 6), 6), '100', 2) : '100.00';

        $this->upsert($b, null, 'kpi_02', $t, $start, $end, $pct, null, ['desviacion' => (string) $row->desv]);
    }

    private function calcFaltantes(int $b, KpiPeriodType $t, $start, $end): void
    {
        $val = (string) (DB::table('vw_kpi_faltantes')->where('business_id', $b)
            ->whereBetween('dia', [$start->toDateString(), $end->toDateString()])
            ->sum('faltante_no_justificado') ?? '0');

        $this->upsert($b, null, 'kpi_03', $t, $start, $end, $val, $this->goalFor($b, 'kpi_03', $t, $start));
    }

    private function calcUsoSistema(int $b, KpiPeriodType $t, $start, $end): void
    {
        // % de usuarios activos que registraron actividad en el período (proxy Fase 1).
        $activos = (int) DB::table('vw_kpi_uso_sistema')->where('business_id', $b)
            ->whereBetween('dia', [$start->toDateString(), $end->toDateString()])
            ->distinct()->count('user_id');
        $total = (int) DB::table('users')->where('business_id', $b)->where('is_active', true)->count();
        $pct = $total > 0 ? bcmul(bcdiv((string) $activos, (string) $total, 6), '100', 2) : '0.00';

        $this->upsert($b, null, 'kpi_04', $t, $start, $end, $pct, $this->goalFor($b, 'kpi_04', $t, $start),
            ['usuarios_activos' => $activos, 'usuarios_totales' => $total]);
    }

    private function calcDisponibilidad(int $b, KpiPeriodType $t, $start, $end): void
    {
        $row = DB::table('vw_kpi_disponibilidad')->where('business_id', $b)
            ->whereBetween('dia', [$start->toDateString(), $end->toDateString()])
            ->selectRaw('COALESCE(SUM(corridas_ok),0) ok, COALESCE(SUM(corridas_totales),0) tot')->first();

        $tot = (int) ($row->tot ?? 0);
        $pct = $tot > 0 ? bcmul(bcdiv((string) $row->ok, (string) $tot, 6), '100', 2) : '100.00';
        $this->upsert($b, null, 'kpi_07', $t, $start, $end, $pct, null, ['corridas' => $tot]);
    }

    // ─────────────────────── KPIs calculados por job (sin vista) ───────────────────────

    /** KPI-01: correspondencia ventas↔caja↔inventario. Promedio de dos brechas (.md). */
    private function calcCorrespondencia(int $b, KpiPeriodType $t, $start, $end): void
    {
        // Brecha venta↔caja: facturas emitidas vs (pagos + CxC generada) en el período.
        $facturado = (string) (DB::table('invoices')->where('business_id', $b)->where('status', 'emitida')
            ->whereBetween('issued_at', [$start, $end])->sum('total') ?? '0');
        $pagado = (string) (DB::table('invoice_payments')->where('business_id', $b)
            ->whereBetween('paid_at', [$start, $end])->sum('amount') ?? '0');
        $creditoCxc = (string) (DB::table('accounts_receivables')->where('business_id', $b)
            ->whereBetween('created_at', [$start, $end])->sum('total_amount') ?? '0');

        $cubierto = bcadd($pagado, $creditoCxc, 2);
        $gapCaja  = bccomp($facturado, '0', 2) > 0
            ? bcmul(bcdiv($this->abs(bcsub($facturado, $cubierto, 2)), $facturado, 6), '100', 2) : '0.00';

        // 100 - brecha promedio (Fase 1: solo brecha caja; venta↔inventario se afina en Fase 2).
        $correspondencia = bcsub('100', $gapCaja, 2);
        if (bccomp($correspondencia, '0', 2) < 0) $correspondencia = '0.00';

        $this->upsert($b, null, 'kpi_01', $t, $start, $end, $correspondencia, null,
            ['facturado' => $facturado, 'cubierto' => $cubierto, 'gap_caja_pct' => $gapCaja]);
    }

    /** KPI-06: cumplimiento de metas. Cruza los snapshots recién escritos contra business_goals. */
    private function calcCumplimientoMetas(int $b, KpiPeriodType $t, $start, $end): void
    {
        $snapshots = KpiSnapshot::query()->where('business_id', $b)
            ->where('period_type', $t->value)->where('period_start', $start->toDateString())
            ->whereNotNull('achievement_pct')->get();

        if ($snapshots->isEmpty()) {
            return;   // sin metas con logro calculado → no hay cumplimiento agregado
        }

        $sum = '0.00';
        foreach ($snapshots as $s) { $sum = bcadd($sum, (string) $s->achievement_pct, 2); }
        $promedio = bcdiv($sum, (string) $snapshots->count(), 2);

        $this->upsert($b, null, 'kpi_06', $t, $start, $end, $promedio, '100.00',
            ['metas_evaluadas' => $snapshots->count()]);
    }

    // ─────────────────────── Helpers ───────────────────────

    /** Cotas del período en el huso del negocio (Fase 1: rango de fechas locales). */
    private function periodBounds(Business $business, KpiPeriodType $type, CarbonInterface $ref): array
    {
        $local = $ref->copy()->setTimezone($business->timezone);
        return match ($type) {
            KpiPeriodType::Diario  => [$local->copy()->startOfDay(),   $local->copy()->endOfDay()],
            KpiPeriodType::Semanal => [$local->copy()->startOfWeek(),  $local->copy()->endOfWeek()],
            KpiPeriodType::Mensual => [$local->copy()->startOfMonth(), $local->copy()->endOfMonth()],
            KpiPeriodType::Anual   => [$local->copy()->startOfYear(),  $local->copy()->endOfYear()],
        };
    }

    /** Meta congelada para el período (si existe en business_goals). */
    private function goalFor(int $b, string $kpiCode, KpiPeriodType $t, $start): ?string
    {
        $goal = DB::table('business_goals')->where('business_id', $b)
            ->where('kpi_code', $kpiCode)->where('period_type', $t->value)
            ->where('period_start', $start->toDateString())->value('target_value');
        return $goal !== null ? (string) $goal : null;
    }

    /** Upsert idempotente respetando el UNIQUE de branch_key. achievement_pct = value/target*100. */
    private function upsert(int $b, ?int $branchId, string $kpiCode, KpiPeriodType $t, $start, $end, string $value, ?string $target = null, ?array $meta = null): void
    {
        $achievement = ($target !== null && bccomp($target, '0', 2) > 0)
            ? bcmul(bcdiv($value, $target, 6), '100', 2) : null;

        KpiSnapshot::query()->updateOrCreate(
            ['business_id' => $b, 'branch_id' => $branchId, 'kpi_code' => $kpiCode,
             'period_type' => $t->value, 'period_start' => $start->toDateString()],
            ['period_end' => $end->toDateString(), 'value' => $value, 'target_value' => $target,
             'achievement_pct' => $achievement, 'metadata' => $meta, 'calculated_at' => now()],
        );
    }

    private function abs(string $d): string
    {
        return bccomp($d, '0', 2) < 0 ? bcmul($d, '-1', 2) : $d;
    }
}
