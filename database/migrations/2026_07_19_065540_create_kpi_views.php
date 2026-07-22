<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // KPI-05 — Evolución de ventas
        DB::statement("CREATE OR REPLACE VIEW vw_kpi_ventas AS
            SELECT business_id, branch_id, DATE(issued_at) AS dia,
                    SUM(total) AS ventas_total, COUNT(*) AS num_facturas,
                    SUM(total)/NULLIF(COUNT(*),0) AS ticket_promedio
            FROM invoices WHERE status='emitida'
            GROUP BY business_id, branch_id, DATE(issued_at)");

        // KPI-08 — Recuperación de cartera
        DB::statement("CREATE OR REPLACE VIEW vw_kpi_cartera AS
            SELECT business_id,
                SUM(total_amount) AS cartera_emitida, SUM(paid_amount) AS cartera_recuperada,
                SUM(balance) AS cartera_pendiente,
                SUM(CASE WHEN status='vencida' THEN balance ELSE 0 END) AS cartera_vencida,
                SUM(paid_amount)/NULLIF(SUM(total_amount),0)*100 AS pct_recuperacion
            FROM accounts_receivables GROUP BY business_id");

        // KPI-02 — Exactitud de stock
        DB::statement("CREATE OR REPLACE VIEW vw_kpi_exactitud_stock AS
            SELECT business_id, DATE(counted_at) AS dia,
                SUM(ABS(difference)) AS desviacion_absoluta, SUM(system_quantity) AS stock_sistema,
                (1-(SUM(ABS(difference))/NULLIF(SUM(system_quantity),0)))*100 AS pct_exactitud
            FROM physical_counts GROUP BY business_id, DATE(counted_at)");

        // KPI-03 — Faltantes no justificados
        DB::statement("CREATE OR REPLACE VIEW vw_kpi_faltantes AS
            SELECT a.business_id, DATE(a.detected_at) AS dia, SUM(ABS(a.difference)) AS faltante_no_justificado
            FROM anomalies a JOIN anomaly_rules r ON r.id=a.anomaly_rule_id
            WHERE r.code='faltante_inventario' AND a.status NOT IN ('justificada','resuelta')
            GROUP BY a.business_id, DATE(a.detected_at)");

        // KPI-04 — Uso del sistema
        DB::statement("CREATE OR REPLACE VIEW vw_kpi_uso_sistema AS
            SELECT business_id, user_id, DATE(created_at) AS dia, COUNT(*) AS acciones
            FROM audit_logs GROUP BY business_id, user_id, DATE(created_at)");

        // KPI-07 — Disponibilidad de reportes
        DB::statement("CREATE OR REPLACE VIEW vw_kpi_disponibilidad AS
            SELECT business_id, DATE(started_at) AS dia,
                SUM(status='completada') AS corridas_ok, COUNT(*) AS corridas_totales,
                SUM(status='completada')/NULLIF(COUNT(*),0)*100 AS pct_disponibilidad
            FROM reconciliation_runs WHERE run_type='programada'
            GROUP BY business_id, DATE(started_at)");
    }

    public function down(): void
    {
        foreach (['vw_kpi_ventas','vw_kpi_cartera','vw_kpi_exactitud_stock',
                'vw_kpi_faltantes','vw_kpi_uso_sistema','vw_kpi_disponibilidad'] as $v) {
            DB::statement("DROP VIEW IF EXISTS {$v}");
        }
    }
};
