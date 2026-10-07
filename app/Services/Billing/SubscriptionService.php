<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Contracts\SubscriptionGateway;
use App\Enums\BillingPeriod;
use App\Enums\SubscriptionStatus;
use App\Exceptions\BillingUnavailableException;
use App\Exceptions\CheckoutIdempotencyConflictException;
use App\Exceptions\CheckoutInProgressException;
use App\Exceptions\CheckoutKeyExpiredException;
use App\Exceptions\CheckoutResultUnknownException;
use App\Exceptions\PlanLimitExceededException;
use App\Exceptions\SubscriptionAlreadyActiveException;
use App\Exceptions\SubscriptionManagementException;
use App\Models\BillingWebhookEvent;
use App\Models\Branch;
use App\Models\Business;
use App\Models\CashSession;
use App\Models\CheckoutIntent;
use App\Models\PlanSubscription;
use App\Models\SubscriptionPayment;
use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Orquesta contratación (checkout alojado), confirmación por webhook verificado, gestión de la MISMA
 * suscripción (cancelación / cambio de plan) y reconciliación. Reglas clave:
 *   - La vigencia (paid_until) SOLO se extiende al insertar una evidencia de pago NUEVA (unique por pago) cuyo
 *     motivo de facturación sea inicial/renovación: eso deduplica reenvíos y eventos distintos y nunca extiende
 *     dos veces; un cobro de PRORRATEO (updated) NO añade un período.
 *   - Un retorno del navegador, subscription_created o status=active NO conceden acceso por sí solos.
 *   - modo/tienda del recurso deben coincidir con el modo activo; se rechazan recursos de otro modo.
 *   - Un evento PROPIO temporalmente no correlacionado se APARCA (recuperable), nunca se pierde tras 200.
 *   - Las llamadas de red ocurren FUERA de transacciones MySQL. Las transacciones locales son cortas.
 */
final class SubscriptionService
{
    private const PAYING_EVENT = 'subscription_payment_success';

    /** Reintentos máximos de reconciliación de un evento aparcado antes de abandonarlo (marcarlo procesado). */
    private const MAX_PARK_ATTEMPTS = 48;

    /** Cancelaciones de suscripciones duplicadas acumuladas durante una transacción, ejecutadas DESPUÉS (red fuera de la transacción). */
    /** @var array<int, string> */
    private array $deferredCancellations = [];

    public function __construct(
        private readonly BillingMode $mode,
        private readonly BillingCatalog $catalog,
        private readonly SubscriptionGateway $gateway,
    ) {
    }

    // =================================================================== CHECKOUT

    public function checkout(Business $business, string $plan, string $period, string $idempotencyKey, ?string $email): CheckoutIntent
    {
        $this->mode->assertCoherent();              // demo/test | commercial/live, si no → BillingUnavailable

        if (! $this->catalog->hasPlan($plan) || ! $this->catalog->hasPeriod($period)) {
            throw new BillingUnavailableException();
        }

        // Un negocio con suscripción vigente NO abre un segundo checkout (evita una segunda suscripción recurrente
        // en el proveedor). El cambio de plan y la cancelación operan sobre la MISMA suscripción.
        $existing = PlanSubscription::query()->where('business_id', $business->id)->first();
        if ($existing !== null && $existing->grantsAccessNow()) {
            throw new SubscriptionAlreadyActiveException();
        }

        $periodEnum  = BillingPeriod::from($period);
        $fingerprint = hash('sha256', $plan.'|'.$period);
        $modeValue   = $this->mode->activeMode()->value;

        // Lock por negocio MANTENIDO durante TODA la sección crítica, INCLUIDA la llamada de red, para serializar
        // de verdad los checkouts del mismo negocio (dos pestañas/claves no dejan dos contrataciones utilizables).
        // La espera para adquirirlo es finita; si no se adquiere, indisponibilidad sanitizada (nunca fabricar URL).
        $lockName = 'gintly_checkout_'.$business->id;
        if (! $this->acquireLock($lockName)) {
            throw new BillingUnavailableException();
        }

        try {
            $now = Carbon::now();

            // Recomprobación AUTORITATIVA de suscripción vigente DENTRO de la sección crítica (evita la carrera con
            // un webhook que la active entre el pre-chequeo y la adquisición del lock).
            $activeNow = PlanSubscription::query()->where('business_id', $business->id)->first();
            if ($activeNow !== null && $activeNow->grantsAccessNow($now)) {
                throw new SubscriptionAlreadyActiveException();
            }

            // 0) RESOLVER intentos no concluyentes del negocio ANTES de decidir crear otro: inciertos (respuesta
            // perdida) y 'pending' de procesos INTERRUMPIDOS. Se consulta al proveedor si creó aquel checkout; si
            // no es concluyente (incompleta/ventana de gracia/no consultable) se BLOQUEA (nunca se recrea a ciegas).
            $this->resolveUnresolvedIntents((int) $business->id, $now);

            // 1) Idempotencia por CLAVE.
            $intent = CheckoutIntent::query()->where('idempotency_key', $idempotencyKey)->first();
            if ($intent !== null) {
                if ((int) $intent->business_id !== (int) $business->id || (string) $intent->fingerprint !== $fingerprint) {
                    throw new CheckoutIdempotencyConflictException();
                }
                if ((string) $intent->status === 'created' && is_string($intent->checkout_url) && $intent->checkout_url !== '') {
                    if ($this->intentIsOpen($intent, $now)) {
                        return $intent; // reutiliza el checkout vigente (idempotente; incluye el recuperado)
                    }
                    // Checkout creado pero VENCIDO: NO se sobrescribe la UUID (se conserva su correlación histórica
                    // por si un pago tardío de esa URL llega); se exige una clave nueva.
                    throw new CheckoutKeyExpiredException();
                }
                // 'failed' (resuelto: el proveedor no lo creó) → se recrea reutilizando esta fila (sin correlación
                // histórica que preservar). No llegan 'uncertain'/'pending': ya se resolvieron o bloquearon arriba.
            }

            // 2) UN SOLO checkout abierto por negocio (serializado por el lock, incluida la red).
            $open = CheckoutIntent::query()
                ->where('business_id', $business->id)
                ->where('status', 'created')
                ->where(function ($q) use ($now): void {
                    $q->whereNull('expires_at')->orWhere('expires_at', '>', $now);
                })
                ->when($intent !== null, fn ($q) => $q->where('id', '!=', $intent->id))
                ->orderByDesc('id')->first();

            if ($open !== null) {
                if ((string) $open->fingerprint === $fingerprint) {
                    // Reutiliza el MISMO checkout del proveedor (no se crea otro) y PERSISTE la asociación idempotente
                    // de ESTA clave apuntando a ese checkout. Así, después, la clave no puede usarse con OTRO payload
                    // como si nunca hubiera recibido respuesta: misma clave+payload ⇒ este checkout; otro payload ⇒
                    // CHECKOUT_IDEMPOTENCY_CONFLICT.
                    $bound = $intent ?? new CheckoutIntent();
                    $bound->forceFill([
                        'business_id'          => $business->id,
                        'idempotency_key'      => $idempotencyKey,
                        'plan_key'             => $open->plan_key,
                        'period'               => $open->period instanceof BillingPeriod ? $open->period->value : (string) $open->period,
                        'provider'             => 'lemon_squeezy',
                        'provider_mode'        => $modeValue,
                        'store_id'             => $open->store_id,
                        'provider_variant_id'  => $open->provider_variant_id,
                        'provider_checkout_id' => $open->provider_checkout_id,
                        'checkout_url'         => $open->checkout_url,
                        'expires_at'           => $open->expires_at,
                        'status'               => 'created',
                        'fingerprint'          => $fingerprint,
                    ])->save();

                    return $bound;
                }
                // OTRA selección mientras el anterior sigue PAGABLE: LS no permite invalidar un checkout ya creado,
                // así que NO se abre otra contratación. Se bloquea hasta que el abierto se complete o venza.
                throw new CheckoutInProgressException();
            }

            // 3) Crear o RECREAR. Se PERSISTE ANTES de la red todo lo necesario para recuperar el intento, INCLUIDO
            // su vencimiento (expires_at); updated_at marca el instante del intento (ventana de gracia). Así un
            // proceso interrumpido deja datos recuperables (status 'pending' o 'uncertain').
            $variantId = $this->catalog->variantId($plan, $periodEnum); // ausencia ⇒ BillingUnavailable
            $storeId   = $this->catalog->storeId();
            $ttl       = min(1440, max(5, (int) config('billing.checkout_ttl_minutes', 60)));
            $expiresAt = $now->copy()->addMinutes($ttl);

            if ($intent === null) {
                $intent = CheckoutIntent::query()->create([
                    'business_id'          => $business->id,
                    'idempotency_key'      => $idempotencyKey,
                    'plan_key'             => $plan,
                    'period'               => $periodEnum->value,
                    'provider'             => 'lemon_squeezy',
                    'provider_mode'        => $modeValue,
                    'store_id'             => $storeId,
                    'provider_variant_id'  => $variantId,
                    'expires_at'           => $expiresAt,
                    'status'               => 'pending',
                    'fingerprint'          => $fingerprint,
                ]);
            } else {
                $intent->forceFill([
                    'plan_key'             => $plan,
                    'period'               => $periodEnum->value,
                    'provider_mode'        => $modeValue,
                    'store_id'             => $storeId,
                    'provider_variant_id'  => $variantId,
                    'provider_checkout_id' => null,
                    'checkout_url'         => null,
                    'expires_at'           => $expiresAt,
                    'status'               => 'pending',
                    'fingerprint'          => $fingerprint,
                ])->save();
            }

            // 4) Red DENTRO del lock. Sin reintento automático de un POST incierto: si falla, se marca 'uncertain'
            // (con su expires_at ya persistido) y se propaga; el próximo intento lo RESOLVERÁ contra el proveedor.
            try {
                $result = $this->gateway->createCheckout([
                    'variant_id'  => (string) $intent->provider_variant_id,
                    'store_id'    => (string) $intent->store_id,
                    'mode'        => $modeValue,
                    'email'       => $email,
                    'business_id' => (int) $business->id,
                    'intent_key'  => (string) $intent->idempotency_key,
                    'return_url'  => config('billing.return_url') !== null ? (string) config('billing.return_url') : null,
                    'expires_at'  => $expiresAt->toIso8601String(),
                ]);
            } catch (\Throwable $e) {
                $intent->forceFill(['status' => 'uncertain'])->save();
                throw $e; // BillingUnavailable (o lo que lance el gateway); no se fabrica URL.
            }

            $intent->forceFill([
                'provider_checkout_id' => $result['id'],
                'checkout_url'         => $result['url'],
                'expires_at'           => $expiresAt,
                'status'               => 'created',
            ])->save();

            return $intent;
        } finally {
            $this->releaseLock($lockName);
        }
    }

    /** ¿El intento sigue abierto/utilizable (no vencido)? expires_at NULL se trata como abierto (compatibilidad). */
    private function intentIsOpen(CheckoutIntent $intent, Carbon $now): bool
    {
        return $intent->expires_at === null || $intent->expires_at->greaterThan($now);
    }

    /**
     * Resuelve los intentos no concluyentes del negocio (inciertos por respuesta perdida y 'pending' de procesos
     * interrumpidos) ANTES de crear otro. Consulta OFICIAL y PAGINADA del proveedor por intent_key:
     *   - 'found' verificado (intent_key/negocio + tienda/modo/variante) ⇒ se adopta (status 'created').
     *   - 'absent' CONCLUYENTE y pasada la ventana de gracia ⇒ 'failed' (el proveedor no lo creó).
     *   - 'incomplete', dentro de la gracia, o no consultable ⇒ se conserva incierto y se BLOQUEA (409), porque la
     *     creación podría seguir procesándose: jamás se recrea a ciegas (evita dos checkouts pagables).
     */
    private function resolveUnresolvedIntents(int $businessId, Carbon $now): void
    {
        $grace     = max(0, (int) config('billing.checkout_recovery_grace_seconds', 90));
        $modeValue = $this->mode->providerMode()->value;
        $storeId   = (string) config('billing.store_id');

        $unresolved = CheckoutIntent::query()
            ->where('business_id', $businessId)
            ->whereIn('status', ['uncertain', 'pending'])
            ->get();

        foreach ($unresolved as $ci) {
            try {
                $result = $this->gateway->findCheckoutByIntentKey((string) $ci->idempotency_key);
            } catch (\Throwable $e) {
                report($e);
                throw new CheckoutResultUnknownException(); // no consultable → no se recrea sin resolver
            }

            $outcome = (string) ($result['outcome'] ?? 'incomplete');

            if ($outcome === 'found') {
                $co = (array) ($result['checkout'] ?? []);
                if (! $this->checkoutMatchesIntent($co, $ci, $modeValue, $storeId)) {
                    throw new CheckoutResultUnknownException(); // coincidencia no verificable → no concluyente
                }
                $ci->forceFill([
                    'provider_checkout_id' => (string) ($co['id'] ?? ''),
                    'checkout_url'         => (string) ($co['url'] ?? ''),
                    'expires_at'           => isset($co['expires_at']) && $co['expires_at'] !== null ? Carbon::parse((string) $co['expires_at']) : $ci->expires_at,
                    'status'               => 'created',
                ])->save();

                continue;
            }

            // Ausente dentro de la gracia (la creación podría seguir procesándose) o búsqueda INCOMPLETA → incierto.
            $attemptedAt = $ci->updated_at ?? $ci->created_at;
            $withinGrace = $attemptedAt !== null && $attemptedAt->diffInSeconds($now, true) < $grace;
            if ($outcome === 'incomplete' || $withinGrace) {
                if ((string) $ci->status !== 'uncertain') {
                    $ci->forceFill(['status' => 'uncertain'])->save();
                }
                throw new CheckoutResultUnknownException();
            }

            // Ausente, CONCLUYENTE y pasada la gracia → el proveedor no lo creó.
            $ci->forceFill(['status' => 'failed'])->save();
        }
    }

    /**
     * Verifica que el checkout hallado en el proveedor corresponde al intento: intent_key y negocio DEBEN coincidir
     * (van en checkout_data.custom que fijamos); tienda, modo y variante se verifican cuando el proveedor los
     * expone. Una discrepancia hace la coincidencia no concluyente (el llamador bloquea).
     *
     * @param  array<string, mixed>  $co
     */
    private function checkoutMatchesIntent(array $co, CheckoutIntent $ci, string $modeValue, string $storeId): bool
    {
        $keyOk = (string) ($co['intent_key'] ?? '') === (string) $ci->idempotency_key;
        $bizOk = (string) ($co['business_id'] ?? '') === (string) $ci->business_id;

        $coVariant = (string) ($co['variant_id'] ?? '');
        $variantOk = $coVariant === '' || $coVariant === (string) $ci->provider_variant_id;

        $coStore = (string) ($co['store_id'] ?? '');
        $storeOk = $storeId === '' || $coStore === '' || $coStore === $storeId;

        $modeOk = ! array_key_exists('test_mode', $co) || ((bool) $co['test_mode']) === ($modeValue === 'test');

        return $keyOk && $bizOk && $variantOk && $storeOk && $modeOk;
    }

    // =================================================================== GESTIÓN (cancelar / cambiar)

    /**
     * Cancela las renovaciones de la MISMA suscripción (conserva el acceso hasta paid_until). La llamada de red
     * va fuera de transacción; el estado local se refleja (idempotente con el webhook subscription_cancelled).
     */
    public function requestCancellation(Business $business): PlanSubscription
    {
        $sub = PlanSubscription::query()->where('business_id', $business->id)->first();
        if ($sub === null || ! $sub->grantsAccessNow()) {
            throw SubscriptionManagementException::noSubscription();
        }
        if ($sub->provider_subscription_id === null) {
            throw SubscriptionManagementException::notProvisioned();
        }

        $this->gateway->cancelSubscription((string) $sub->provider_subscription_id);

        $sub->status = SubscriptionStatus::Canceled->value;
        $sub->canceled_at = Carbon::now();
        // NO toca paid_until: el acceso se conserva hasta el fin del período ya pagado.
        $sub->pending_plan_key = null;
        $sub->pending_period = null;
        $sub->pending_effective_at = null;
        $sub->save();

        return $sub;
    }

    /**
     * Cambia de plan CONSERVANDO la misma suscripción.
     *   - Ascenso: cambia la variante en el proveedor facturando el prorrateo de inmediato; las capacidades NO se
     *     elevan hasta que llega la evidencia del pago (webhook de pago con motivo 'updated'). Se registra el
     *     cambio como pendiente (pending_effective_at = null ⇒ "al confirmarse el pago").
     *   - Descenso: verifica que el USO ACTUAL cabe en el plan destino (sin borrar recursos); programa el cambio
     *     al cierre del período pagado (pending_effective_at = paid_until) y ajusta la variante del proveedor con
     *     disable_prorations (solo gobierna el dinero del prorrateo, NO es la programación). Las capacidades siguen
     *     siendo las del plan actual hasta el límite del período.
     */
    public function requestPlanChange(Business $business, string $plan, string $period): PlanSubscription
    {
        $this->mode->assertCoherent();

        if (! $this->catalog->hasPlan($plan) || ! $this->catalog->hasPeriod($period)) {
            throw new BillingUnavailableException();
        }

        // Coordina la comprobación de uso, la persistencia del pendiente y las altas/reactivaciones con los MISMOS
        // locks de límite del negocio (orden fijo: sucursales, luego cajas); relectura DENTRO de la sección crítica.
        // La red (PATCH) queda FUERA de los locks.
        $branchesLock = 'gintly_plan_branches_'.$business->id;
        $cashLock     = 'gintly_plan_cash_sessions_'.$business->id;
        if (! $this->acquireLock($branchesLock)) {
            throw new BillingUnavailableException();
        }
        if (! $this->acquireLock($cashLock)) {
            $this->releaseLock($branchesLock);
            throw new BillingUnavailableException();
        }

        $targetVariant = '';
        $providerSubId = '';
        $isUpgrade = false;
        $sub = null;

        try {
            $sub = PlanSubscription::query()->where('business_id', $business->id)->first();
            if ($sub === null || ! $sub->grantsAccessNow()) {
                throw SubscriptionManagementException::noSubscription();
            }
            if ($sub->provider_subscription_id === null) {
                throw SubscriptionManagementException::notProvisioned();
            }

            $currentPlan   = (string) $sub->plan_key;
            $currentPeriod = $this->periodOf($sub)->value;
            if ($plan === $currentPlan && $period === $currentPeriod) {
                throw SubscriptionManagementException::sameSelection();
            }

            $targetVariant = $this->catalog->variantId($plan, BillingPeriod::from($period));
            $providerSubId = (string) $sub->provider_subscription_id;
            $currentRank   = $this->catalog->tierRank($currentPlan);
            $targetRank    = $this->catalog->tierRank($plan);
            // Ascenso = sube de nivel de plan, o mantiene nivel pero pasa de mensual a anual (más cobertura).
            $isUpgrade = $targetRank > $currentRank
                || ($targetRank === $currentRank && $currentPeriod === 'monthly' && $period === 'annual');

            if (! $isUpgrade) {
                // DESCENSO: el uso actual debe caber en el destino (bajo los mismos locks que las altas; sin borrar).
                $this->assertUsageFits((int) $business->id, $plan);
            }

            // PERSISTE el cambio ANTES de la red, de modo que un webhook del proveedor (p. ej. el pago del prorrateo
            // del ascenso) que llegue MIENTRAS se llama al proveedor ya encuentre el cambio correlacionado.
            // provider_variant_id NO se cambia aquí: se fija al CONFIRMAR (con la variante ACTUAL del proveedor), de
            // modo que el plan siempre refleje lo cobrado. pending_variant_id guarda la variante destino esperada.
            $sub->pending_plan_key     = $plan;
            $sub->pending_period       = $period;
            $sub->pending_variant_id   = $targetVariant;
            $sub->pending_effective_at = $isUpgrade ? null : $sub->paid_until; // ascenso: al pago; descenso: al cierre
            $sub->save();
        } finally {
            $this->releaseLock($cashLock);
            $this->releaseLock($branchesLock);
        }

        // Red FUERA de los locks. El PATCH de variante es idempotente; si falla, el cambio queda pendiente y la
        // confirmación (variante ACTUAL del proveedor vía recurso oficial) decide: nunca se cobra un plan sin aplicar.
        $this->gateway->updateSubscriptionVariant($providerSubId, $targetVariant, $isUpgrade, ! $isUpgrade);

        return $sub;
    }

    /** El uso REAL del negocio cabe en el plan destino; si no, 409 PLAN_LIMIT_EXCEEDED (sin borrar recursos). */
    private function assertUsageFits(int $businessId, string $plan): void
    {
        $branchLimit = $this->catalog->limit($plan, 'branches');
        if ($branchLimit !== null) {
            $active = (int) Branch::withoutGlobalScopes()
                ->where('business_id', $businessId)->whereNull('deleted_at')->where('is_active', true)->count();
            if ($active > $branchLimit) {
                throw PlanLimitExceededException::branches($branchLimit);
            }
        }

        $cashLimit = $this->catalog->limit($plan, 'cash_sessions');
        if ($cashLimit !== null) {
            $open = (int) CashSession::withoutGlobalScopes()
                ->where('business_id', $businessId)->whereIn('status', ['abierta', 'descuadrada'])->count();
            if ($open > $cashLimit) {
                throw PlanLimitExceededException::cashSessions($cashLimit);
            }
        }
    }

    // =================================================================== WEBHOOK

    /**
     * Procesa un webhook YA verificado (firma comprobada por el controlador sobre el cuerpo original). Devuelve
     * una etiqueta de resultado. Nunca concede acceso sin evidencia de pago. Guarda el cuerpo para recuperar
     * eventos propios aún no correlacionables.
     *
     * @param  array<string, mixed>  $payload
     */
    public function processVerifiedWebhook(array $payload): string
    {
        $eventName = (string) Arr::get($payload, 'meta.event_name', '');
        $modeValue = $this->mode->providerMode()->value; // el modo configurado del backend
        $identity  = $this->eventIdentity($payload, $eventName);
        $rawJson   = json_encode($payload);

        // Dedup por identidad persistente (provider, mode, identity). INSERT-first race-safe.
        try {
            $event = BillingWebhookEvent::query()->create([
                'provider'       => 'lemon_squeezy',
                'provider_mode'  => $modeValue,
                'event_identity' => $identity,
                'event_name'     => $eventName,
                'payload'        => $rawJson === false ? null : $rawJson,
                'received_at'    => Carbon::now(),
            ]);
        } catch (QueryException $e) {
            if ((int) ($e->errorInfo[1] ?? 0) !== 1062) {
                throw $e;
            }
            $event = BillingWebhookEvent::query()
                ->where('provider', 'lemon_squeezy')->where('provider_mode', $modeValue)
                ->where('event_identity', $identity)->first();
            if ($event !== null && $event->processed_at !== null) {
                return 'duplicate'; // ya procesado → idempotente
            }
            if ($event === null) {
                return 'uncorrelated'; // defensivo (no debería ocurrir)
            }
            // Reentrega de un evento aún no procesado (p. ej. aparcado): cuenta el reintento y conserva el cuerpo.
            $event->attempts = (int) $event->attempts + 1;
            if ($event->payload === null && $rawJson !== false) {
                $event->payload = $rawJson;
            }
            $event->save();
        }

        return $this->applyEvent($event, $payload, $eventName, $modeValue, $identity);
    }

    /**
     * Aplica un evento (nuevo, reentregado o reproducido en reconciliación). Decide coherencia de modo/tienda,
     * correlación segura y ciclo de vida. Marca procesado SOLO tras aplicar con éxito.
     *
     * @param  array<string, mixed>  $payload
     */
    private function applyEvent(BillingWebhookEvent $event, array $payload, string $eventName, string $modeValue, string $identity): string
    {
        // Coherencia de modo del RECURSO: en commercial se rechaza test y viceversa (aunque la firma sea válida).
        $resourceTestMode = (bool) Arr::get($payload, 'data.attributes.test_mode', false);
        $expectedTest     = $modeValue === 'test';
        if ($resourceTestMode !== $expectedTest) {
            $this->markProcessed($event); // ajeno a este modo: definitivo

            return 'mode_mismatch';
        }

        // Tienda esperada.
        $storeId = (string) Arr::get($payload, 'data.attributes.store_id', '');
        if ($storeId !== '' && (string) config('billing.store_id') !== '' && $storeId !== (string) config('billing.store_id')) {
            $this->markProcessed($event);

            return 'store_mismatch';
        }

        $subscription = $this->correlate($payload, $modeValue);
        if ($subscription === null) {
            // ¿Es PROPIO pero aún no correlacionable (fuera de orden) o definitivamente ajeno?
            if ($this->looksOwnButUnresolved($payload, $modeValue)) {
                $this->park($event);

                return 'parked';
            }
            $this->markProcessed($event); // ajeno/no accionable

            return 'uncorrelated';
        }

        // Para un evento de pago con un cambio de plan PENDIENTE, se consulta la variante ACTUAL de la suscripción
        // en el recurso OFICIAL del proveedor (las subscription-invoices no traen variante). Se hace FUERA de la
        // transacción (red). Si no es consultable, queda null: la AUSENCIA de confirmación NO confirma el cambio.
        $providerVariant = $this->currentProviderVariant($eventName, $subscription);

        $this->deferredCancellations = [];
        $applied = true;

        DB::transaction(function () use ($eventName, $payload, $subscription, $identity, $modeValue, $event, $providerVariant, &$applied): void {
            // Relectura CON BLOQUEO de la fila de suscripción: SERIALIZA las modificaciones concurrentes de la MISMA
            // suscripción (p. ej. dos facturas distintas a la vez). El UNIQUE de pago no basta: cada transacción lee
            // el paid_until ya confirmado por la otra y extiende de forma correcta (sin pérdida de actualización).
            $locked = PlanSubscription::query()->whereKey($subscription->id)->lockForUpdate()->first() ?? $subscription;

            if ($event->business_id === null) {
                $event->business_id = $locked->business_id; // auditoría
            }

            $applied = $this->applyLifecycle($eventName, $payload, $locked, $identity, $modeValue, $providerVariant);
            if ($applied) {
                $this->markProcessed($event); // procesado SOLO tras aplicar con éxito (misma transacción)
            }
        });

        if (! $applied) {
            // No resolvible todavía (p. ej. reembolso recibido ANTES de su pago): se APARCA (recuperable) sin
            // marcarse procesado; la reconciliación/reentrega lo resuelve cuando llegue el pago. No se pierde.
            $this->park($event);

            return 'parked';
        }

        // Cancelación de suscripciones DUPLICADAS del proveedor: red SIEMPRE fuera de la transacción MySQL.
        $this->flushDeferredCancellations();

        return 'processed';
    }

    private function flushDeferredCancellations(): void
    {
        $ids = array_values(array_unique($this->deferredCancellations));
        $this->deferredCancellations = [];
        foreach ($ids as $subId) {
            $this->bestEffortCancel($subId);
        }
    }

    /**
     * Variante ACTUAL de la suscripción según el recurso OFICIAL del proveedor, SOLO cuando hace falta confirmar un
     * cambio de plan pendiente ante un evento de pago. Red FUERA de transacción; null si no es consultable (la
     * ausencia de confirmación NO confirma el cambio).
     */
    private function currentProviderVariant(string $eventName, PlanSubscription $sub): ?string
    {
        if ($eventName !== self::PAYING_EVENT || $sub->pending_plan_key === null || $sub->provider_subscription_id === null) {
            return null;
        }
        try {
            return (string) ($this->gateway->getSubscription((string) $sub->provider_subscription_id)['variant_id'] ?? '');
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }

    /**
     * ¿El evento sin correlación es PROPIO (recuperable) o definitivamente ajeno? Con custom_data.intent_key
     * propio y coherente ⇒ propio (transitorio). Sin custom_data, solo es candidato propio si referencia una
     * suscripción del proveedor (pago/renovación fuera de orden). En otro caso es ajeno y no se aparca.
     *
     * @param  array<string, mixed>  $payload
     */
    private function looksOwnButUnresolved(array $payload, string $modeValue): bool
    {
        $intentKey = (string) Arr::get($payload, 'meta.custom_data.intent_key', '');
        if ($intentKey !== '') {
            $intent = CheckoutIntent::query()->where('idempotency_key', $intentKey)->first();

            return $intent !== null && (string) $intent->provider_mode === $modeValue;
        }

        $type = (string) Arr::get($payload, 'data.type', '');
        $providerSubId = $type === 'subscriptions'
            ? (string) Arr::get($payload, 'data.id', '')
            : (string) Arr::get($payload, 'data.attributes.subscription_id', '');

        return $providerSubId !== '';
    }

    /**
     * Correlación SEGURA: por provider_subscription_id ya persistido o por intent_key (checkout propio). Nunca
     * vincula por email ni por un business_id del payload sin respaldo local. Crea la fila de suscripción (una
     * por negocio) en la primera correlación por intento.
     *
     * @param  array<string, mixed>  $payload
     */
    private function correlate(array $payload, string $modeValue): ?PlanSubscription
    {
        $type   = (string) Arr::get($payload, 'data.type', '');
        $dataId = (string) Arr::get($payload, 'data.id', '');
        $providerSubId = $type === 'subscriptions'
            ? $dataId
            : (string) Arr::get($payload, 'data.attributes.subscription_id', '');

        // 1) Suscripción ya conocida por su id de proveedor (eventos de suscripción o de su factura/pago).
        if ($providerSubId !== '') {
            $sub = PlanSubscription::query()
                ->where('provider', 'lemon_squeezy')->where('provider_mode', $modeValue)
                ->where('provider_subscription_id', $providerSubId)->first();
            if ($sub !== null) {
                return $sub;
            }
        }

        // 2) Primer evento: correlación por el intento de checkout propio (intent_key + business_id).
        $intentKey  = (string) Arr::get($payload, 'meta.custom_data.intent_key', '');
        $customBiz  = (int) Arr::get($payload, 'meta.custom_data.business_id', 0);
        if ($intentKey === '' || $customBiz <= 0) {
            return null;
        }

        $intent = CheckoutIntent::query()->where('idempotency_key', $intentKey)->first();
        if ($intent === null || (int) $intent->business_id !== $customBiz || (string) $intent->provider_mode !== $modeValue) {
            return null; // business_id del payload no respaldado por un intento propio coherente
        }

        // Una suscripción por negocio (se reutiliza; no se crean paralelas).
        $sub = PlanSubscription::query()->firstOrCreate(
            ['business_id' => $intent->business_id],
            [
                'plan_key'                 => $intent->plan_key,
                'period'                   => $intent->period instanceof BillingPeriod ? $intent->period->value : (string) $intent->period,
                'status'                   => SubscriptionStatus::Incomplete->value,
                'provider'                 => 'lemon_squeezy',
                'provider_mode'            => $modeValue,
                'store_id'                 => $intent->store_id,
                'provider_subscription_id' => $providerSubId !== '' ? $providerSubId : null,
                'provider_variant_id'      => $intent->provider_variant_id,
            ]
        );

        // RE-CONTRATACIÓN legítima: un negocio SIN vigencia (fila existente, vencida/incompleta) contrata de nuevo y
        // el proveedor le asigna OTRO provider_subscription_id. Se RE-VINCULA la MISMA fila (conserva business_id e
        // historial de pagos) al nuevo id, en vez de tratar su pago como suscripción paralela y cancelarlo. Si la
        // fila SÍ tiene vigencia, NO se re-vincula: un segundo id pagando en paralelo es una duplicación indebida
        // (la detecta applyPaidInvoice y la cancela).
        if ($providerSubId !== '' && (string) $sub->provider_subscription_id !== $providerSubId && ! $sub->grantsAccessNow()) {
            $sub->provider_subscription_id = $providerSubId;
            $sub->provider_mode = $modeValue;
            $sub->store_id = $intent->store_id;
            $sub->plan_key = $intent->plan_key;
            $sub->period = $intent->period instanceof BillingPeriod ? $intent->period->value : (string) $intent->period;
            $sub->provider_variant_id = $intent->provider_variant_id;
            $sub->canceled_at = null;
            $sub->pending_plan_key = null;
            $sub->pending_period = null;
            $sub->pending_variant_id = null;
            $sub->pending_effective_at = null;
            $sub->status = SubscriptionStatus::Incomplete->value;
            $sub->save();
        }

        return $sub;
    }

    /**
     * Aplica el ciclo de vida del evento sobre la suscripción (fila ya BLOQUEADA por el llamador). Devuelve false
     * cuando el evento NO es resolvible todavía (p. ej. reembolso recibido ANTES de su pago): el llamador lo
     * APARCA para resolverlo luego, sin perderlo.
     *
     * @param  array<string, mixed>  $payload
     */
    private function applyLifecycle(string $eventName, array $payload, PlanSubscription $sub, string $identity, string $modeValue, ?string $providerVariant = null): bool
    {
        // Asegura el id de suscripción del proveedor en la fila local (para correlacionar renovaciones).
        $providerSubId = (string) Arr::get($payload, 'data.id', '');
        if ($sub->provider_subscription_id === null && $providerSubId !== '' && (string) Arr::get($payload, 'data.type') === 'subscriptions') {
            $sub->provider_subscription_id = $providerSubId;
        }

        switch ($eventName) {
            case 'subscription_created':
                if (! $sub->grantsAccessNow()) {
                    $sub->status = SubscriptionStatus::Incomplete->value;
                }
                $sub->save();

                return true;

            case self::PAYING_EVENT:
                $this->applyPaidInvoice($sub, $this->invoiceFromPayload($payload), $identity, $modeValue, $providerVariant);

                return true;

            case 'subscription_payment_failed':
                // Renovación fallida: NO extiende; marca past_due (conserva acceso hasta paid_until vigente).
                $sub->status = SubscriptionStatus::PastDue->value;
                $sub->save();

                return true;

            case 'subscription_cancelled':
                // Cancelación de renovaciones: conserva acceso hasta el fin ya pagado.
                $sub->status = SubscriptionStatus::Canceled->value;
                $sub->canceled_at = $sub->canceled_at ?? Carbon::now();
                $sub->save();

                return true;

            case 'subscription_expired':
                // Un 'expired' ANTIGUO/fuera de orden NO retrocede una cobertura ya RENOVADA: si paid_until sigue
                // vigente (futuro), se reconoce SIN efecto (no revoca un estado confirmado). Solo expira si la
                // vigencia realmente pasó.
                if ($sub->paid_until !== null && $sub->paid_until->greaterThan(Carbon::now())) {
                    report(new \RuntimeException('subscription_expired obsoleto ignorado (suscripción '.$sub->id.': vigencia futura).'));

                    return true;
                }
                $sub->status = SubscriptionStatus::Expired->value;
                $sub->save();

                return true;

            case 'subscription_payment_refunded':
                return $this->applyRefund($payload, $sub); // false ⇒ reembolso antes del pago → aparcar y resolver luego

            default:
                // Otros eventos (p. ej. subscription_updated): reconocidos; la vigencia no cambia aquí.
                return true;
        }
    }

    /**
     * Normaliza la factura/pago a la forma canónica de evidencia, usando SOLO campos OFICIALES de
     * subscription-invoices (NO existe variant_id en la factura: la variante vive en la suscripción). created_at es
     * la fecha oficial que ancla la cobertura al período realmente pagado.
     *
     * @param  array<string, mixed>  $payload
     * @return array{id:string, total:int, currency:string, billing_reason:string, subscription_id:string, created_at:?string}
     */
    private function invoiceFromPayload(array $payload): array
    {
        return [
            // Identidad CANÓNICA del pago = id de la subscription-invoice (no el order ni el subscription id).
            'id'              => (string) Arr::get($payload, 'data.id', ''),
            'total'           => (int) Arr::get($payload, 'data.attributes.total', 0),
            'currency'        => (string) Arr::get($payload, 'data.attributes.currency', 'USD'),
            'billing_reason'  => (string) Arr::get($payload, 'data.attributes.billing_reason', ''),
            'subscription_id' => (string) Arr::get($payload, 'data.attributes.subscription_id', ''),
            'created_at'      => Arr::get($payload, 'data.attributes.created_at') !== null ? (string) Arr::get($payload, 'data.attributes.created_at') : null,
        ];
    }

    /**
     * Inserta la evidencia de pago (unique por provider_payment_id) y ajusta la vigencia según el motivo. La
     * cobertura se calcula desde el PERÍODO realmente pagado (no desde la fecha de recepción ni copiando una
     * renovación impagada): en tiempo real continúa desde paid_until; una factura RECUPERADA ($isRecovery) ancla
     * en su fecha oficial, por lo que una histórica NO concede tiempo nuevo.
     *
     * @param  array{id:string, total:int, currency:string, billing_reason:string, subscription_id:string, created_at:?string}  $inv
     */
    private function applyPaidInvoice(PlanSubscription $sub, array $inv, string $identity, string $modeValue, ?string $providerVariant = null): void
    {
        $paymentId = $inv['id'];
        if ($paymentId === '') {
            return;
        }

        $duplicateContract = $sub->provider_subscription_id !== null
            && $inv['subscription_id'] !== ''
            && $inv['subscription_id'] !== (string) $sub->provider_subscription_id;

        $isProration = $inv['billing_reason'] === 'updated';

        // RENOVACIÓN que cierra el período: aplica el DESCENSO programado ANTES de registrar/extender, para quedar
        // COHERENTE con la variante/importe que el proveedor cobra. Solo si la variante ACTUAL de la suscripción en
        // el proveedor coincide con la del cambio: NUNCA se aplica un plan distinto del cobrado. Igual para webhook
        // y reconciliación; independiente del scheduler.
        if (! $duplicateContract && ! $isProration && $inv['billing_reason'] === 'renewal' && $this->downgradeEffective($sub)) {
            $this->applyPendingDowngradeNow($sub, $providerVariant);
        }

        // Ancla de cobertura: SIEMPRE la fecha OFICIAL de la factura (consistente para webhook y reconciliación). Así
        // cada factura cubre SU período (no desde "ahora"): una histórica/tardía no proyecta al futuro y dos facturas
        // concurrentes conservan sus períodos (paid_until = máximo, sin sumar). Sin created_at, continúa desde la
        // vigencia pagada.
        $anchor      = $inv['created_at'] !== null ? Carbon::parse((string) $inv['created_at']) : null;
        $coverageEnd = $this->coverageEnd($sub, $anchor);

        if ($duplicateContract) {
            $periodStart = Carbon::now();
            $periodEnd   = Carbon::now();
            $status      = 'duplicate';
        } elseif ($isProration) {
            $periodStart = $sub->current_period_start ?? Carbon::now();
            $periodEnd   = $sub->paid_until ?? Carbon::now(); // el prorrateo NO añade un período
            $status      = 'paid';
        } else {
            $periodStart = $anchor !== null ? $anchor->copy() : $this->coverageBase($sub);
            $periodEnd   = $coverageEnd;
            $status      = 'paid';
        }

        try {
            SubscriptionPayment::query()->create([
                'business_id'          => $sub->business_id,
                'plan_subscription_id' => $sub->id,
                'provider'             => 'lemon_squeezy',
                'provider_mode'        => $modeValue,
                'provider_payment_id'  => $paymentId,
                'event_identity'       => $identity,
                'status'               => $status,
                'amount_minor'         => $inv['total'],
                'currency'             => $inv['currency'],
                'catalog_amount_minor' => $this->catalog->catalogMinor((string) $sub->plan_key, $this->periodOf($sub)),
                'catalog_currency'     => (string) config('billing.catalog_currency', 'NIO'),
                'period_start'         => $periodStart,
                'period_end'           => $periodEnd,
            ]);
        } catch (QueryException $e) {
            if ((int) ($e->errorInfo[1] ?? 0) === 1062) {
                return; // pago ya contabilizado (reenvío/evento duplicado o recuperado) → NO reprocesar ni reactivar
            }
            throw $e;
        }

        if ($duplicateContract) {
            // No extiende; deja constancia y ENCOLA la cancelación de la suscripción duplicada para DESPUÉS de la
            // transacción (la red nunca va dentro de una transacción MySQL).
            report(new \RuntimeException('Pago de suscripción de proveedor DUPLICADA para el negocio '.$sub->business_id.' (sub '.$inv['subscription_id'].').'));
            $this->deferredCancellations[] = $inv['subscription_id'];

            return;
        }

        if ($isProration) {
            // Prorrateo cobrado: confirma el ASCENSO pendiente CORRESPONDIENTE (la variante ACTUAL del proveedor
            // coincide con la del cambio) sin añadir tiempo.
            $this->applyPendingUpgrade($sub, $providerVariant);
            if (! in_array($sub->status, [SubscriptionStatus::Active, SubscriptionStatus::Canceled], true)) {
                $sub->status = SubscriptionStatus::Active->value;
            }
            $sub->save();

            return;
        }

        // inicial | renovación → la vigencia NUNCA se encoge; una factura RECUPERADA histórica no concede tiempo
        // nuevo (max con la vigencia actual) ni reactiva si su cobertura ya pasó.
        $newPaidUntil = $this->maxDate($sub->paid_until, $coverageEnd);
        $sub->paid_until = $newPaidUntil;
        if ($newPaidUntil->greaterThan(Carbon::now())) {
            $sub->current_period_start = $sub->current_period_start ?? $periodStart;
            $sub->status = SubscriptionStatus::Active->value;
            Business::query()->whereKey($sub->business_id)->update(['plan' => $sub->plan_key]);
        }
        $sub->save();
    }

    /**
     * Confirma un ASCENSO pendiente (pending sin fecha ⇒ "al pago"). Solo el pago CORRESPONDIENTE al cambio lo
     * confirma: si la factura expone variante, DEBE coincidir con la variante destino del cambio. Actualiza plan,
     * periodicidad y provider_variant_id de forma coherente.
     */
    private function applyPendingUpgrade(PlanSubscription $sub, ?string $providerVariant): void
    {
        if ($sub->pending_plan_key === null || $sub->pending_effective_at !== null) {
            return; // sin pendiente, o es un descenso programado (se aplica en el límite del período)
        }
        if ($this->catalog->tierRank((string) $sub->pending_plan_key) < $this->catalog->tierRank((string) $sub->plan_key)) {
            return; // nunca baja capacidades por un pago
        }
        // Correlación POSITIVA: el proveedor debe reportar la variante del cambio como la ACTUAL de la suscripción.
        // La ausencia (null/'') NO confirma el ascenso (la factura no trae variante; se consulta el recurso oficial).
        if ($providerVariant === null || $sub->pending_variant_id === null || $providerVariant !== (string) $sub->pending_variant_id) {
            return;
        }

        $sub->plan_key = (string) $sub->pending_plan_key;
        if ($sub->pending_period !== null) {
            $sub->period = (string) $sub->pending_period;
        }
        if ($sub->pending_variant_id !== null) {
            $sub->provider_variant_id = (string) $sub->pending_variant_id;
        }
        $sub->pending_plan_key = null;
        $sub->pending_period = null;
        $sub->pending_variant_id = null;
        $sub->pending_effective_at = null;

        Business::query()->whereKey($sub->business_id)->update(['plan' => $sub->plan_key]);
    }

    /** ¿Hay un DESCENSO programado cuyo período pagado ya cerró (pending con fecha efectiva vencida)? */
    private function downgradeEffective(PlanSubscription $sub, ?Carbon $now = null): bool
    {
        $now ??= Carbon::now();

        return $sub->pending_plan_key !== null
            && $sub->pending_effective_at !== null
            && $sub->pending_effective_at->lessThanOrEqualTo($now);
    }

    /**
     * Aplica el DESCENSO programado EN MEMORIA (plan_key/period/variant del destino) + espejo businesses.plan, y
     * limpia el pendiente. NO guarda la fila (lo hace el llamador). Sin grandfathering: durante el descenso
     * pendiente las altas/reactivaciones se acotan al plan DESTINO (PlanLimits), de modo que el uso SIEMPRE cabe al
     * aplicar; no se borran recursos. Solo se aplica si la variante cobrada coincide con la del cambio (cuando se
     * expone): nunca se aplica un plan distinto del cobrado.
     */
    private function applyPendingDowngradeNow(PlanSubscription $sub, ?string $providerVariant): void
    {
        if ($sub->pending_plan_key === null) {
            return;
        }
        // Correlación POSITIVA: solo se aplica si el proveedor reporta la variante del descenso como la ACTUAL de la
        // suscripción (coherente con lo que cobra). Si no se pudo consultar (null) o no coincide, NO se aplica: se
        // reintenta en la reconciliación. Nunca se aplica un plan distinto del cobrado.
        if ($providerVariant === null || $sub->pending_variant_id === null || $providerVariant !== (string) $sub->pending_variant_id) {
            return;
        }

        $target = (string) $sub->pending_plan_key;
        $period = $sub->pending_period !== null ? (string) $sub->pending_period : $this->periodOf($sub)->value;

        $sub->plan_key = $target;
        $sub->period = $period;
        if ($sub->pending_variant_id !== null) {
            $sub->provider_variant_id = (string) $sub->pending_variant_id;
        }
        $sub->pending_plan_key = null;
        $sub->pending_period = null;
        $sub->pending_variant_id = null;
        $sub->pending_effective_at = null;

        Business::query()->whereKey($sub->business_id)->update(['plan' => $target]);
    }

    /**
     * Resuelve un reembolso. Devuelve false si el pago AÚN no consta (reembolso recibido antes de su pago): el
     * llamador lo aparca para resolverlo cuando llegue el pago (no se pierde). Idempotente si ya estaba reembolsado.
     *
     * @param  array<string, mixed>  $payload
     */
    private function applyRefund(array $payload, PlanSubscription $sub): bool
    {
        $paymentId = (string) Arr::get($payload, 'data.id', '');
        $payment = SubscriptionPayment::query()
            ->where('plan_subscription_id', $sub->id)->where('provider_payment_id', $paymentId)->first();

        if ($payment === null) {
            return false; // reembolso ANTES del pago → aparcar; se resolverá cuando el pago se registre
        }

        $full = (bool) Arr::get($payload, 'data.attributes.refunded', true);

        // Idempotencia SIN perder una escalada: un reembolso TOTAL ya aplicado no se reprocesa; pero un total que
        // llega DESPUÉS de uno parcial SÍ debe aplicarse (revoca). Un parcial sobre un parcial no cambia nada.
        if ((string) $payment->status === 'refunded') {
            return true; // ya reembolsado TOTAL (terminal)
        }
        if ((string) $payment->status === 'partial_refund' && ! $full) {
            return true; // ya parcial; otro parcial no altera
        }

        $payment->status = $full ? 'refunded' : 'partial_refund';
        $payment->save();

        // Reembolso TOTAL del pago que sostiene el período vigente → revoca cobertura. Parcial → sin tiempo extra.
        if ($full && $payment->period_end !== null && $sub->paid_until !== null
            && $payment->period_end->equalTo($sub->paid_until)) {
            $sub->paid_until = Carbon::now();
            $sub->status = SubscriptionStatus::Expired->value;
            $sub->save();
        }

        return true;
    }

    private function bestEffortCancel(string $providerSubscriptionId): void
    {
        if ($providerSubscriptionId === '') {
            return;
        }
        try {
            $this->gateway->cancelSubscription($providerSubscriptionId);
        } catch (\Throwable $e) {
            report($e); // la reconciliación/soporte reintentará; nunca rompe el procesamiento del pago primario.
        }
    }

    // =================================================================== RECONCILIACIÓN / VENCIMIENTO

    /**
     * Pasada de reconciliación (la invoca el scheduler, sin colas nuevas): vence vigencias pasadas, aplica
     * descensos cuyo período ya cerró, reproduce eventos propios aparcados y recupera pagos cuyo webhook se
     * perdió mediante consulta OFICIAL al proveedor.
     *
     * @return array{expired:int, downgraded:int, replayed:int, recovered:int}
     */
    public function reconcile(?Carbon $now = null): array
    {
        $now ??= Carbon::now();

        return [
            'expired'    => $this->expireDue($now),
            'downgraded' => $this->applyDuePlanChanges($now),
            'replayed'   => $this->reconcilePendingWebhooks($now),
            'recovered'  => $this->reconcileFromProvider(),
        ];
    }

    /** Vence las suscripciones cuya vigencia pagada ya pasó, aunque el proveedor siga intentando cobrar. */
    public function expireDue(?Carbon $now = null): int
    {
        $now ??= Carbon::now();

        return PlanSubscription::query()
            ->whereIn('status', [
                SubscriptionStatus::Active->value,
                SubscriptionStatus::Canceled->value,
                SubscriptionStatus::PastDue->value,
            ])
            ->where(function ($q) use ($now): void {
                $q->whereNull('paid_until')->orWhere('paid_until', '<=', $now);
            })
            ->update(['status' => SubscriptionStatus::Expired->value]);
    }

    /**
     * BACKSTOP del scheduler para los DESCENSOS cuyo período pagado ya cerró, cuando el webhook de renovación no
     * llegó. Durante el pendiente las altas/reactivaciones ya estuvieron acotadas al plan DESTINO (PlanLimits), de
     * modo que el uso cabe sin borrar recursos. Se aplica BAJO los mismos locks de negocio que las altas (relectura
     * dentro de la sección crítica; idempotente con el webhook) y SOLO si el proveedor reporta ya la variante del
     * destino como actual (red FUERA del lock): nunca se aplica un plan distinto del cobrado.
     */
    public function applyDuePlanChanges(?Carbon $now = null): int
    {
        $now ??= Carbon::now();

        $due = PlanSubscription::query()
            ->whereNotNull('pending_plan_key')
            ->whereNotNull('pending_effective_at')
            ->where('pending_effective_at', '<=', $now)
            ->get();

        $applied = 0;
        foreach ($due as $sub) {
            // Variante ACTUAL del proveedor (red FUERA del lock). Null si no es consultable → no se aplica (reintenta).
            $providerVariant = null;
            if ($sub->provider_subscription_id !== null) {
                try {
                    $providerVariant = (string) ($this->gateway->getSubscription((string) $sub->provider_subscription_id)['variant_id'] ?? '');
                } catch (\Throwable $e) {
                    report($e);
                }
            }

            $applied += $this->withBusinessLimitLocks((int) $sub->business_id, function () use ($sub, $now, $providerVariant): int {
                // Relee bajo lock: si el webhook de renovación ya lo aplicó, no hay nada que hacer (idempotente).
                $fresh = PlanSubscription::query()->find($sub->id);
                if ($fresh === null || ! $this->downgradeEffective($fresh, $now)) {
                    return 0;
                }

                $this->applyPendingDowngradeNow($fresh, $providerVariant);
                if ($fresh->pending_plan_key !== null) {
                    return 0; // no se aplicó (variante aún no coincide / no consultable): se reintenta
                }
                $fresh->save();

                return 1;
            });
        }

        return $applied;
    }

    /**
     * Ejecuta $fn con los DOS locks de límite del negocio (sucursales y cajas), adquiridos en orden fijo para no
     * generar ciclos con los tenedores de un solo lock. Si alguno no se adquiere, devuelve 0 sin ejecutar (la
     * reconciliación reintentará): un descenso NO se aplica sin serializar frente a altas concurrentes.
     *
     * @param  Closure():int  $fn
     */
    private function withBusinessLimitLocks(int $businessId, Closure $fn): int
    {
        $branchesLock = 'gintly_plan_branches_'.$businessId;
        $cashLock     = 'gintly_plan_cash_sessions_'.$businessId;

        if (! $this->acquireLock($branchesLock)) {
            return 0;
        }
        if (! $this->acquireLock($cashLock)) {
            $this->releaseLock($branchesLock);

            return 0;
        }

        try {
            return $fn();
        } finally {
            $this->releaseLock($cashLock);
            $this->releaseLock($branchesLock);
        }
    }

    /** Reproduce eventos PROPIOS aparcados (recibidos pero no correlacionados). Abandona tras demasiados intentos. */
    public function reconcilePendingWebhooks(?Carbon $now = null): int
    {
        $now ??= Carbon::now();

        $parked = BillingWebhookEvent::query()
            ->whereNotNull('parked_at')->whereNull('processed_at')
            ->orderBy('id')->limit(500)->get();

        $processed = 0;
        foreach ($parked as $event) {
            $payload = json_decode((string) $event->payload, true);
            if (! is_array($payload)) {
                $this->markProcessed($event); // cuerpo irrecuperable → no reintentar indefinidamente

                continue;
            }

            $event->attempts = (int) $event->attempts + 1;
            $result = $this->applyEvent($event, $payload, (string) $event->event_name, (string) $event->provider_mode, (string) $event->event_identity);

            if ($result === 'processed') {
                $processed++;

                continue;
            }
            if ((int) $event->attempts >= self::MAX_PARK_ATTEMPTS) {
                $this->markProcessed($event); // abandono acotado: nunca se acumula para siempre
            } else {
                $event->save(); // persiste attempts; sigue aparcado
            }
        }

        return $processed;
    }

    /**
     * Consulta OFICIAL y PAGINADA al proveedor para recuperar notificaciones perdidas. Para cada suscripción local
     * con id de proveedor (incluidas las VENCIDAS localmente), lee sus facturas y:
     *   - factura PAGADA no reembolsada y aún no registrada → la aplica como RECUPERADA (ancla en su fecha: una
     *     histórica NO concede tiempo nuevo; una renovación perdida reactiva con evidencia válida).
     *   - factura REEMBOLSADA cuyo pago local aún no está reembolsado → concilia el reembolso (revoca si sostenía la
     *     vigencia). Nunca se reactiva una cobertura ya revocada (dedup por provider_payment_id).
     * No fabrica pagos ni concede acceso sin una factura pagada real.
     */
    public function reconcileFromProvider(): int
    {
        $modeValue = $this->mode->providerMode()->value;
        $this->deferredCancellations = [];

        $subs = PlanSubscription::query()
            ->whereNotNull('provider_subscription_id')
            ->where('provider_mode', $modeValue)
            ->whereIn('status', [
                SubscriptionStatus::Active->value,
                SubscriptionStatus::PastDue->value,
                SubscriptionStatus::Canceled->value,
                SubscriptionStatus::Incomplete->value,
                SubscriptionStatus::Expired->value, // vencidas localmente: pueden tener una renovación pagada real
            ])
            ->get();

        $recovered = 0;
        foreach ($subs as $sub) {
            try {
                $invoices = $this->gateway->listSubscriptionInvoices((string) $sub->provider_subscription_id);
            } catch (\Throwable $e) {
                report($e); // proveedor indisponible: se omite este negocio, se reintenta en la próxima pasada.

                continue;
            }

            // Variante ACTUAL del proveedor (solo si hay un cambio pendiente que confirmar). Red FUERA de la tx.
            $providerVariant = null;
            if ($sub->pending_plan_key !== null) {
                try {
                    $providerVariant = (string) ($this->gateway->getSubscription((string) $sub->provider_subscription_id)['variant_id'] ?? '');
                } catch (\Throwable $e) {
                    report($e);
                }
            }

            foreach ($invoices as $invoice) {
                $invoiceId = (string) ($invoice['id'] ?? '');
                if ($invoiceId === '') {
                    continue;
                }

                $payment = SubscriptionPayment::query()
                    ->where('provider', 'lemon_squeezy')->where('provider_mode', $modeValue)
                    ->where('provider_payment_id', $invoiceId)->first();

                // Reembolso conciliado por consulta: si el pago consta y aún no está reembolsado, se revoca igual
                // que por webhook (sin reactivar nada; una cobertura ya revocada no se recupera).
                if ((bool) ($invoice['refunded'] ?? false)) {
                    if ($payment !== null && ! in_array((string) $payment->status, ['refunded', 'partial_refund'], true)) {
                        DB::transaction(function () use ($sub, $invoiceId): void {
                            $locked = PlanSubscription::query()->whereKey($sub->id)->lockForUpdate()->first() ?? $sub;
                            $this->applyRefund([
                                'data' => ['id' => $invoiceId, 'attributes' => ['refunded' => true]],
                            ], $locked);
                        });
                        $recovered++;
                    }

                    continue;
                }

                if (($invoice['status'] ?? '') !== 'paid' || $payment !== null) {
                    continue; // no pagada, o ya registrada (dedup → ni doble conteo ni re-grant de algo revocado)
                }

                $normalized = [
                    'id'              => $invoiceId,
                    'total'           => (int) ($invoice['total'] ?? 0),
                    'currency'        => (string) ($invoice['currency'] ?? 'USD'),
                    'billing_reason'  => (string) ($invoice['billing_reason'] ?? ''),
                    'subscription_id' => (string) $sub->provider_subscription_id,
                    'created_at'      => isset($invoice['created_at']) ? (string) $invoice['created_at'] : null,
                ];

                $identity = hash('sha256', 'recovered|subscription-invoices|'.$invoiceId);
                DB::transaction(function () use ($sub, $normalized, $identity, $modeValue, $providerVariant): void {
                    $locked = PlanSubscription::query()->whereKey($sub->id)->lockForUpdate()->first() ?? $sub;
                    $this->applyPaidInvoice($locked, $normalized, $identity, $modeValue, $providerVariant);
                });
                $recovered++;
            }
        }

        $this->flushDeferredCancellations();

        return $recovered;
    }

    // =================================================================== HELPERS

    private function periodOf(PlanSubscription $sub): BillingPeriod
    {
        return $sub->period instanceof BillingPeriod ? $sub->period : BillingPeriod::from((string) $sub->period);
    }

    /** Base de cobertura en TIEMPO REAL: continúa desde la vigencia pagada (no desde la fecha de recepción). */
    private function coverageBase(PlanSubscription $sub): Carbon
    {
        $now = Carbon::now();

        return ($sub->paid_until !== null && $sub->paid_until->greaterThan($now)) ? $sub->paid_until->copy() : $now;
    }

    /**
     * Fin de cobertura = base + período del catálogo. En tiempo real la base es la continuación del período pagado;
     * una factura RECUPERADA ancla en su fecha oficial ($anchor), de modo que una histórica no proyecta al futuro.
     */
    private function coverageEnd(PlanSubscription $sub, ?Carbon $anchor = null): Carbon
    {
        $base = $anchor !== null ? $anchor->copy() : $this->coverageBase($sub);

        return $this->periodOf($sub)->extend($base);
    }

    /** Mayor de dos fechas (la vigencia nunca se encoge). */
    private function maxDate(?Carbon $a, Carbon $b): Carbon
    {
        return ($a !== null && $a->greaterThan($b)) ? $a->copy() : $b->copy();
    }

    /** @param array<string, mixed> $payload */
    private function eventIdentity(array $payload, string $eventName): string
    {
        $type    = (string) Arr::get($payload, 'data.type', '');
        $id      = (string) Arr::get($payload, 'data.id', '');
        $version = (string) (Arr::get($payload, 'data.attributes.updated_at')
            ?? Arr::get($payload, 'data.attributes.created_at') ?? '');

        return hash('sha256', $eventName.'|'.$type.'|'.$id.'|'.$version);
    }

    private function markProcessed(BillingWebhookEvent $event): void
    {
        $event->processed_at = Carbon::now();
        $event->save();
    }

    private function park(BillingWebhookEvent $event): void
    {
        $event->parked_at = $event->parked_at ?? Carbon::now();
        $event->save();
    }

    private function acquireLock(string $name): bool
    {
        $timeout = min(60, max(1, (int) config('billing.lock_timeout_seconds', 10)));

        try {
            $row = DB::select('SELECT GET_LOCK(?, ?) AS locked', [$name, $timeout], false);

            return isset($row[0]->locked) && (int) $row[0]->locked === 1;
        } catch (\Throwable $e) {
            report($e);

            return false;
        }
    }

    private function releaseLock(string $name): void
    {
        try {
            DB::select('SELECT RELEASE_LOCK(?)', [$name], false);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
