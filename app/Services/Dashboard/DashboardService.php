<?php

declare(strict_types=1);

namespace App\Services\Dashboard;

use App\Enums\AccountReceivableStatus;
use App\Enums\CashSessionStatus;
use App\Models\AccountPayable;
use App\Models\AccountReceivable;
use App\Models\Anomaly;
use App\Models\CashSession;
use App\Models\Dispatch;
use App\Models\GoodsReceipt;
use App\Models\PhysicalCount;
use App\Models\Sale;
use App\Models\User;

/**
 * Fase 7 · Agregados de dashboard sobre DATOS REALES existentes (sin métricas inventadas ni entidad
 * de solicitudes). Todo se filtra por el negocio de la sesión (BusinessScope) y, para el operativo,
 * por la sucursal del usuario y sus perfiles. Es un contrato de LECTURA agregada; las Policies por
 * recurso siguen siendo la autoridad al abrir cada elemento.
 */
final class DashboardService
{
    /** Estados activos de anomalía (pendientes de gestión). */
    private const ANOMALY_ACTIVE = ['detectada', 'notificada', 'en_revision'];

    /**
     * Dashboard ROL-02 (administrativo): pendientes operativos reales de TODO el negocio.
     * @return array<string, int>
     */
    public function admin(): array
    {
        return [
            'anomalias_activas'          => Anomaly::query()->whereIn('status', self::ANOMALY_ACTIVE)->count(),
            'recepciones_en_discrepancia' => GoodsReceipt::query()->whereIn('match_status', ['discrepancia', 'bloqueada'])->count(),
            'cuentas_por_pagar_congeladas' => AccountPayable::query()->where('status', 'congelada')->count(),
            'cuentas_por_cobrar_vencidas' => AccountReceivable::query()->where('status', AccountReceivableStatus::Vencida->value)->count(),
            'sesiones_caja_abiertas'      => CashSession::query()->where('status', CashSessionStatus::Abierta->value)->count(),
            'ventas_abiertas'             => Sale::query()->where('status', 'abierta')->count(),
        ];
    }

    /**
     * Dashboard ROL-03 (operativo): secciones CONDICIONADAS por los perfiles del usuario y acotadas a
     * SU sucursal. Solo incluye la sección de un perfil si el usuario lo tiene.
     * @return array<string, mixed>
     */
    public function operative(User $user): array
    {
        $branchId = $user->branch_id;
        $sections = [];

        if ($user->operativeCan('caja.abrir') || $user->operativeCan('cuentas_por_cobrar.abonar')) {
            $openSession = CashSession::query()
                ->where('opened_by', $user->id)
                ->where('status', CashSessionStatus::Abierta->value)
                ->value('id');

            $sections['cajero'] = [
                'sesion_caja_abierta'    => $openSession,
                'cxc_cobrables'          => AccountReceivable::query()
                    ->whereIn('status', AccountReceivableStatus::exposureStatuses())
                    ->whereHas('invoice', fn ($i) => $i->where('branch_id', $branchId))
                    ->count(),
            ];
        }

        if ($user->operativeCan('ventas.crear')) {
            $sections['facturador'] = [
                'ventas_abiertas' => Sale::query()->where('status', 'abierta')->where('branch_id', $branchId)->count(),
            ];
        }

        if ($user->operativeCan('inventario.conteo')) {
            $sections['bodeguero'] = [
                'conteos_abiertos'  => PhysicalCount::query()->where('status', 'abierto')
                    ->whereHas('warehouse', fn ($w) => $w->where('branch_id', $branchId))->count(),
                'recepciones_en_discrepancia' => GoodsReceipt::query()->whereIn('match_status', ['discrepancia', 'bloqueada'])
                    ->whereHas('warehouse', fn ($w) => $w->where('branch_id', $branchId))->count(),
            ];
        }

        if ($user->operativeCan('entregas.crear')) {
            $sections['despachador'] = [
                'despachos_de_sucursal' => Dispatch::query()->where('branch_id', $branchId)->count(),
            ];
        }

        return [
            'branch_id' => $branchId,
            'profiles'  => $user->profileValues(),
            'sections'  => $sections,
        ];
    }
}
