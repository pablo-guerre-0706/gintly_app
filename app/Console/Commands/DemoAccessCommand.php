<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Business;
use App\Models\DemoAccessGrant;
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
        {--reason= : Required explanation for granting evaluation access}
        {--target=* : Additional exact business slug:ID; repeat to operate atomically on several businesses}';

    protected $description = 'Grant, revoke or inspect explicit, expiring evaluation access for selected businesses; never simulates payment.';

    public function handle(CommercialAccess $access): int
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
            $targets = [(int) $expectedId => $slug];
            foreach ((array) $this->option('target') as $target) {
                if (! is_string($target) || ! preg_match('/^([^:]+):([1-9][0-9]*)$/D', $target, $parts)
                    || isset($targets[(int) $parts[2]]) || in_array($parts[1], $targets, true)) {
                    $this->error('Use --target=slug:ID únicos y exactos. No se escribió nada.');

                    return self::FAILURE;
                }
                $targets[(int) $parts[2]] = $parts[1];
            }
            if (count($targets) > 50) {
                $this->error('Máximo 50 negocios por operación explícita.');

                return self::FAILURE;
            }
            ksort($targets); // Consistent locking order for concurrent batches.

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
            $businesses = [];
            foreach ($targets as $id => $targetSlug) {
                $business = Business::query()->whereKey($id)->where('slug', $targetSlug)->first();
                if ($business === null) {
                    $this->error('Un ID y slug no identifican el mismo negocio existente. No se escribió nada.');

                    return self::FAILURE;
                }
                $businesses[] = $business;
            }

            if ($action === 'grant') {
                $days = (string) $this->option('days');
                $plan = (string) $this->option('plan');
                $reason = trim((string) $this->option('reason'));
                foreach ($businesses as $business) {
                    if (! $access->canGrantDemoTo($business)) {
                        $this->error('Solo negocios operables, seleccionados y explícitamente habilitados en demo/test. Para varios, habilite BILLING_DEMO_MULTIPLE_BUSINESSES.');

                        return self::FAILURE;
                    }
                }
                if (! preg_match('/^[1-9][0-9]*$/D', $days) || (int) $days > (int) config('billing.demo_access.max_days', 30)
                    || ! array_key_exists($plan, (array) config('billing.catalog', []))
                    || $reason === '' || mb_strlen($reason) > 255) {
                    $this->error('Use 1–30 días, un plan del catálogo y --reason de 1–255 caracteres.');

                    return self::FAILURE;
                }
            }

            $stage = 'grant-storage';
            $grants = $action === 'status'
                ? DemoAccessGrant::query()->whereIn('business_id', array_keys($targets))->get()->keyBy('business_id')->all()
                : DB::transaction(function () use ($action, $businesses, $access): array {
                    $grants = [];
                    foreach ($businesses as $business) {
                        $locked = Business::query()->whereKey($business->id)->where('slug', $business->slug)->lockForUpdate()->firstOrFail();
                        if ($action === 'grant' && ! $access->canGrantDemoTo($locked)) {
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

                        $grants[$business->id] = $grant;
                    }

                    return $grants;
                });
            foreach ($businesses as $business) {
                $grant = $grants[$business->id] ?? null;
                $this->info(sprintf('Negocio %d (%s), BD %s: %s. Vence: %s. Acceso efectivo: %s. No se creó pago ni suscripción.',
                    $business->id, $business->slug, $expectedDatabase,
                    $grant === null ? 'sin concesión' : ($grant->isCurrent() ? 'concesión vigente' : 'concesión revocada/vencida'),
                    $grant?->expires_at?->toIso8601String() ?? 'no aplica',
                    $access->grantsAccess((int) $business->id) ? 'sí' : 'no'));
            }

            return self::SUCCESS;
        } catch (\Throwable $error) {
            // Never dump connection strings, provider secrets or SQL parameters on the console.
            $this->error('No se completó la operación demo en etapa '.$stage.'. Revise configuración/conexión; no reintente sin consultar status.');

            return self::FAILURE;
        }
    }
}
