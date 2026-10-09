<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Business;
use App\Models\DemoAccessGrant;
use App\Services\Billing\BillingMode;
use App\Services\Billing\CommercialAccess;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Privileged console operation only; no HTTP route, provider call or payment fixture. */
final class DemoAccessCommand extends Command
{
    protected $signature = 'billing:demo-access {action : grant, revoke or status} {business : Exact business slug}
        {--database= : Expected effective database name} {--business-id= : Expected business ID}
        {--days=7 : Expiry in days, maximum 30} {--plan=cadena : Existing catalog key}
        {--reason= : Required explanation for granting evaluation access}';

    protected $description = 'Grant, revoke or inspect temporary access for the explicitly selected evaluation business; never simulates payment.';

    public function handle(BillingMode $mode, CommercialAccess $access): int
    {
        $stage = 'arguments';
        try {
            $action = (string) $this->argument('action');
            $slug = (string) $this->argument('business');
            $expectedDatabase = (string) $this->option('database');
            $expectedId = (string) $this->option('business-id');
            if (! in_array($action, ['grant', 'revoke', 'status'], true)
                || $expectedDatabase === '' || ! preg_match('/^[1-9][0-9]*$/D', $expectedId)) {
                $this->error('Indique acción, slug exacto, --database y --business-id positivos.');

                return self::FAILURE;
            }

            $stage = 'database';
            $connection = DB::connection();
            $actualDatabase = match ($connection->getDriverName()) {
                'mysql' => $connection->getPdo()->query('SELECT DATABASE()')->fetchColumn(),
                'sqlite' => app()->environment('testing') && $connection->getDatabaseName() === ':memory:' ? ':memory:' : null,
                default => null,
            };
            if ($actualDatabase !== $expectedDatabase || ! Schema::hasTable('demo_access_grants')) {
                $this->error('Destino efectivo distinto del confirmado, o falta la migración de concesiones demo. No se escribió nada.');

                return self::FAILURE;
            }

            $stage = 'business';
            $business = Business::query()->whereKey((int) $expectedId)->where('slug', $slug)->first();
            if ($business === null) {
                $this->error('El ID y el slug no identifican el mismo negocio existente.');

                return self::FAILURE;
            }

            if ($action === 'grant') {
                $days = (string) $this->option('days');
                $plan = (string) $this->option('plan');
                $reason = trim((string) $this->option('reason'));
                if (config('billing.demo_access.enabled') !== true
                    || (string) config('billing.demo_access.business_slug') !== $slug
                    || ! $mode->isDemo() || $mode->activeMode()->value !== 'test'
                    || ! $business->status?->canOperate()) {
                    $this->error('Solo se concede al negocio configurado, operable y explícitamente habilitado en demo/test.');

                    return self::FAILURE;
                }
                if (! preg_match('/^[1-9][0-9]*$/D', $days) || (int) $days > (int) config('billing.demo_access.max_days', 30)
                    || ! array_key_exists($plan, (array) config('billing.catalog', []))
                    || $reason === '' || mb_strlen($reason) > 255) {
                    $this->error('Use 1–30 días, un plan del catálogo y --reason de 1–255 caracteres.');

                    return self::FAILURE;
                }
            }

            $stage = 'grant-storage';
            $grant = $action === 'status'
                ? DemoAccessGrant::query()->where('business_id', $business->id)->first()
                : DB::transaction(function () use ($action, $business): ?DemoAccessGrant {
                    $locked = Business::query()->whereKey($business->id)->where('slug', $business->slug)->lockForUpdate()->firstOrFail();
                    if ($action === 'grant' && ! $locked->status?->canOperate()) {
                        throw new \LogicException('Business no longer operable.');
                    }
                    $grant = DemoAccessGrant::query()->where('business_id', $business->id)->first();
                    if ($action === 'grant') {
                        $grant ??= new DemoAccessGrant(['business_id' => $business->id]);
                        $grant->fill(['plan_key' => (string) $this->option('plan'),
                            'reason' => trim((string) $this->option('reason')), 'starts_at' => now(),
                            'expires_at' => now()->addDays((int) $this->option('days')), 'revoked_at' => null])->save();
                    } elseif ($action === 'revoke' && $grant !== null && $grant->revoked_at === null) {
                        $grant->revoked_at = now();
                        $grant->save();
                    }

                    return $grant;
                });
            $this->info(sprintf('Negocio %d (%s), BD %s: %s. Vence: %s. Acceso efectivo: %s. No se creó pago ni suscripción.',
                $business->id, $business->slug, $expectedDatabase,
                $grant === null ? 'sin concesión' : ($grant->isCurrent() ? 'concesión vigente' : 'concesión revocada/vencida'),
                $grant?->expires_at?->toIso8601String() ?? 'no aplica',
                $access->grantsAccess((int) $business->id) ? 'sí' : 'no'));

            return self::SUCCESS;
        } catch (\Throwable $error) {
            // Never dump connection strings, provider secrets or SQL parameters on the console.
            $this->error('No se completó la operación demo en etapa '.$stage.'. Revise configuración/conexión; no reintente sin consultar status.');

            return self::FAILURE;
        }
    }
}
