<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // KPI-05 · Ventas por negocio/sucursal/día (facturas emitidas).
        DB::statement(<<<'SQL'
            CREATE OR REPLACE VIEW vw_kpi_ventas AS
            SELECT business_id, branch_id, DATE(issued_at) AS day,
                   COALESCE(SUM(total), 0) AS total_sold,
                   COUNT(*)               AS invoice_count
            FROM invoices
            WHERE status = 'emitida'
            GROUP BY business_id, branch_id, DATE(issued_at)
        SQL);

        // KPI-08 · Cartera por negocio (estado acumulado de CxC).
        DB::statement(<<<'SQL'
            CREATE OR REPLACE VIEW vw_kpi_cartera AS
            SELECT business_id,
                   COALESCE(SUM(total_amount), 0) AS emitida,
                   COALESCE(SUM(paid_amount), 0)  AS recuperada,
                   COALESCE(SUM(balance), 0)      AS pendiente,
                   COALESCE(SUM(CASE WHEN status = 'vencida' THEN balance ELSE 0 END), 0) AS vencida
            FROM accounts_receivables
            GROUP BY business_id
        SQL);

        // KPI-02 · Exactitud de stock por negocio/día (conteos físicos).
        DB::statement(<<<'SQL'
            CREATE OR REPLACE VIEW vw_kpi_exactitud_stock AS
            SELECT business_id, DATE(counted_at) AS day,
                   COALESCE(SUM(ABS(difference)), 0)   AS abs_deviation,
                   COALESCE(SUM(system_quantity), 0)   AS system_total,
                   COUNT(*)                            AS count_total
            FROM physical_counts
            GROUP BY business_id, DATE(counted_at)
        SQL);

        // KPI-03 · Faltantes NO justificados = anomalías activas de faltante_inventario.
        DB::statement(<<<'SQL'
            CREATE OR REPLACE VIEW vw_kpi_faltantes AS
            SELECT a.business_id, DATE(a.detected_at) AS day,
                   COALESCE(SUM(ABS(a.difference)), 0) AS unjustified_shortage,
                   COUNT(*)                            AS shortage_count
            FROM anomalies a
            JOIN anomaly_rules r ON r.id = a.anomaly_rule_id AND r.code = 'faltante_inventario'
            WHERE a.status IN ('detectada', 'notificada', 'en_revision')
            GROUP BY a.business_id, DATE(a.detected_at)
        SQL);

        // KPI-04 · Uso del sistema por negocio/usuario/día (bitácora de auditoría).
        DB::statement(<<<'SQL'
            CREATE OR REPLACE VIEW vw_kpi_uso_sistema AS
            SELECT business_id, user_id, DATE(created_at) AS day,
                   COUNT(*) AS action_count
            FROM audit_logs
            WHERE user_id IS NOT NULL
            GROUP BY business_id, user_id, DATE(created_at)
        SQL);

        // KPI-07 · Disponibilidad = corridas programadas completadas sobre el total.
        DB::statement(<<<'SQL'
            CREATE OR REPLACE VIEW vw_kpi_disponibilidad AS
            SELECT business_id, DATE(started_at) AS day,
                   COALESCE(SUM(CASE WHEN status = 'completada' THEN 1 ELSE 0 END), 0) AS completed,
                   COUNT(*) AS total
            FROM reconciliation_runs
            WHERE run_type = 'programada'
            GROUP BY business_id, DATE(started_at)
        SQL);
    }

    public function down(): void
    {
        foreach ([
            'vw_kpi_ventas', 'vw_kpi_cartera', 'vw_kpi_exactitud_stock',
            'vw_kpi_faltantes', 'vw_kpi_uso_sistema', 'vw_kpi_disponibilidad',
        ] as $view) {
            DB::statement("DROP VIEW IF EXISTS `{$view}`");
        }
    }
};
