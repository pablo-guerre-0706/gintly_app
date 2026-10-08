<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Contracts\SubscriptionGateway;

/**
 * Doble de prueba LIMPIO del proveedor: no hace red. createCheckout devuelve una URL determinista; la
 * verificación de firma REFLEJA la real (HMAC-SHA256 con el secreto configurado) para poder firmar webhooks
 * de prueba. Verifica lógica LOCAL; no sustituye el recorrido sandbox real.
 */
final class FakeSubscriptionGateway implements SubscriptionGateway
{
    /** @var array<int, array<string, mixed>> */
    public array $checkoutCalls = [];

    /** @var array<int, array<string, mixed>> */
    public array $cancelCalls = [];

    /** @var array<int, array<string, mixed>> */
    public array $updateCalls = [];

    public ?\Throwable $failCheckoutWith = null;

    /** Facturas a devolver por listSubscriptionInvoices(), indexadas por provider_subscription_id. */
    /** @var array<string, array<int, array<string, mixed>>> */
    public array $invoicesBySubscription = [];

    /** Checkouts que el proveedor "tiene" por intent_key (para simular resultado incierto con creación real). */
    /** @var array<string, array<string, mixed>> */
    public array $checkoutsByIntentKey = [];

    /** Si no es null, findCheckoutByIntentKey lanza (proveedor inconsultable → no se puede resolver). */
    public ?\Throwable $failFindWith = null;

    /** outcome por defecto cuando el intent_key no está en el mapa: 'absent' (completo) o 'incomplete' (no concluyente). */
    public string $findOutcomeDefault = 'absent';

    /** Hook invocado DENTRO de updateSubscriptionVariant antes de responder (simula un webhook recibido antes de la respuesta). */
    public ?\Closure $onUpdateVariant = null;

    /** Variante ACTUAL que el proveedor reporta por suscripción (recurso oficial getSubscription). */
    /** @var array<string, string> */
    public array $subscriptionVariant = [];

    /** Si no es null, getSubscription lanza (proveedor no consultable). */
    public ?\Throwable $failGetSubscriptionWith = null;

    public function createCheckout(array $params): array
    {
        $this->checkoutCalls[] = $params;

        if ($this->failCheckoutWith !== null) {
            throw $this->failCheckoutWith;
        }

        return [
            'id'  => 'fake_co_'.substr(hash('sha256', (string) ($params['intent_key'] ?? '')), 0, 16),
            'url' => 'https://checkout.test/'.($params['intent_key'] ?? 'x'),
        ];
    }

    public function verifySignature(string $rawPayload, ?string $signature): bool
    {
        $secret = (string) config('billing.webhook_secret');

        if ($secret === '' || ! is_string($signature) || $signature === '') {
            return false;
        }

        return hash_equals(hash_hmac('sha256', $rawPayload, $secret), $signature);
    }

    public function findCheckoutByIntentKey(string $intentKey): array
    {
        if ($this->failFindWith !== null) {
            throw $this->failFindWith;
        }
        if (isset($this->checkoutsByIntentKey[$intentKey])) {
            return ['outcome' => 'found', 'checkout' => $this->checkoutsByIntentKey[$intentKey]];
        }

        return ['outcome' => $this->findOutcomeDefault, 'checkout' => null];
    }

    public function getSubscription(string $providerSubscriptionId): array
    {
        if ($this->failGetSubscriptionWith !== null) {
            throw $this->failGetSubscriptionWith;
        }

        return [
            'variant_id' => $this->subscriptionVariant[$providerSubscriptionId] ?? '',
            'status'     => 'active',
            'renews_at'  => null,
        ];
    }

    public function cancelSubscription(string $providerSubscriptionId): array
    {
        $this->cancelCalls[] = ['id' => $providerSubscriptionId];

        return ['status' => 'cancelled', 'ends_at' => null];
    }

    public function updateSubscriptionVariant(string $providerSubscriptionId, string $variantId, bool $invoiceImmediately, bool $disableProrations): array
    {
        $this->updateCalls[] = [
            'id'                  => $providerSubscriptionId,
            'variant_id'          => $variantId,
            'invoice_immediately' => $invoiceImmediately,
            'disable_prorations'  => $disableProrations,
        ];

        // Simula un webhook del proveedor recibido ANTES de que esta llamada responda (carrera persist-before-network).
        if ($this->onUpdateVariant !== null) {
            ($this->onUpdateVariant)();
        }

        return ['status' => 'active'];
    }

    public function listSubscriptionInvoices(string $providerSubscriptionId): array
    {
        return $this->invoicesBySubscription[$providerSubscriptionId] ?? [];
    }
}
