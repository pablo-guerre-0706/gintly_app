<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Contracts\SubscriptionGateway;
use App\Exceptions\BillingUnavailableException;
use Illuminate\Support\Facades\Http;

/**
 * Implementación REST oficial de Lemon Squeezy con el cliente HTTP de Laravel (sin SDK comunitario). Checkout
 * ALOJADO restringido a la variante (cantidad 1, sin descuentos, sin que el comprador cambie el plan). El
 * `test_mode` se deriva del modo del proveedor. No construye formularios de tarjeta ni expone secretos.
 */
final class LemonSqueezyGateway implements SubscriptionGateway
{
    public function __construct(private readonly BillingMode $mode)
    {
    }

    public function createCheckout(array $params): array
    {
        $apiKey = (string) config('billing.api_key');
        if ($apiKey === '') {
            // Sin credenciales NO se fabrica una URL: indisponibilidad sanitizada.
            throw new BillingUnavailableException();
        }

        $testMode = $this->mode->activeMode()->value === 'test';

        $body = [
            'data' => [
                'type' => 'checkouts',
                'attributes' => [
                    'checkout_data' => [
                        'email'  => $params['email'] ?? null,
                        'custom' => [
                            'business_id' => (string) $params['business_id'],
                            'intent_key'  => $params['intent_key'],
                        ],
                    ],
                    // Restringe la contratación: cantidad fija, sin que el comprador altere el plan.
                    'product_options' => [
                        'redirect_url' => $params['return_url'] ?? null,
                        'enabled_variants' => [(int) $params['variant_id']],
                    ],
                    // Opciones OFICIALES fijadas explícitamente conforme al catálogo aprobado: se OMITE cualquier
                    // prueba gratuita de la variante (skip_trial), se oculta el campo de descuento (no cupones/promos)
                    // y no se incrusta. Cantidad fija por la variante (enabled_variants).
                    'checkout_options' => ['embed' => false, 'discount' => false, 'skip_trial' => true],
                    // Vencimiento FINITO de la URL del checkout (ISO-8601); el proveedor la invalida al pasar.
                    'expires_at' => $params['expires_at'] ?? null,
                    'test_mode' => $testMode,
                ],
                'relationships' => [
                    'store'   => ['data' => ['type' => 'stores', 'id' => (string) $params['store_id']]],
                    'variant' => ['data' => ['type' => 'variants', 'id' => (string) $params['variant_id']]],
                ],
            ],
        ];

        try {
            $response = Http::withToken($apiKey)
                ->acceptJson()
                ->contentType('application/vnd.api+json')
                ->timeout((int) config('billing.http_timeout', 15))
                ->post(rtrim((string) config('billing.api_base'), '/').'/checkouts', $body);
        } catch (\Throwable $e) {
            report($e);
            throw new BillingUnavailableException();
        }

        if (! $response->successful()) {
            report(new \RuntimeException('Lemon Squeezy checkout HTTP '.$response->status()));
            throw new BillingUnavailableException();
        }

        $id  = $response->json('data.id');
        $url = $response->json('data.attributes.url');

        if (! is_string($id) || ! is_string($url) || $url === '') {
            throw new BillingUnavailableException();
        }

        return ['id' => $id, 'url' => $url];
    }

    public function verifySignature(string $rawPayload, ?string $signature): bool
    {
        $secret = (string) config('billing.webhook_secret');

        if ($secret === '' || ! is_string($signature) || $signature === '') {
            return false;
        }

        $expected = hash_hmac('sha256', $rawPayload, $secret);

        return hash_equals($expected, $signature);
    }

    public function findCheckoutByIntentKey(string $intentKey): array
    {
        $storeId = (string) config('billing.store_id');
        // Consulta oficial, PAGINADA y acotada de los checkouts de la tienda; se busca el intent_key en custom.
        $paged = $this->requestPaged('/checkouts?filter[store_id]='.rawurlencode($storeId), (int) config('billing.provider_page_cap', 10));

        foreach ($paged['rows'] as $row) {
            $attr = (array) ($row['attributes'] ?? []);
            $custom = (array) ($attr['checkout_data']['custom'] ?? []);
            if (($custom['intent_key'] ?? null) !== $intentKey) {
                continue;
            }
            $url = (string) ($attr['url'] ?? '');
            if ($url === '') {
                continue;
            }

            return ['outcome' => 'found', 'checkout' => [
                'id'          => (string) ($row['id'] ?? ''),
                'url'         => $url,
                'expires_at'  => isset($attr['expires_at']) ? (string) $attr['expires_at'] : null,
                'store_id'    => (string) ($attr['store_id'] ?? $storeId),
                'test_mode'   => (bool) ($attr['test_mode'] ?? false),
                'variant_id'  => (string) ($row['relationships']['variant']['data']['id'] ?? ($attr['variant_id'] ?? '')),
                'business_id' => (string) ($custom['business_id'] ?? ''),
                'intent_key'  => (string) ($custom['intent_key'] ?? ''),
            ]];
        }

        // No hallado: 'absent' solo si la búsqueda fue COMPLETA; si quedó corta (tope/respuesta inválida) es 'incomplete'.
        return ['outcome' => $paged['complete'] ? 'absent' : 'incomplete', 'checkout' => null];
    }

    public function getSubscription(string $providerSubscriptionId): array
    {
        // Recurso OFICIAL de la suscripción: su variante ACTUAL vive aquí (attributes.variant_id), NO en las
        // subscription-invoices. Se usa para correlacionar que el proveedor aplicó el cambio de variante.
        $response = $this->request('get', '/subscriptions/'.rawurlencode($providerSubscriptionId));
        $attr = (array) ($response['data']['attributes'] ?? []);

        return [
            'variant_id' => (string) ($attr['variant_id'] ?? ($response['data']['relationships']['variant']['data']['id'] ?? '')),
            'status'     => (string) ($attr['status'] ?? ''),
            'renews_at'  => isset($attr['renews_at']) ? (string) $attr['renews_at'] : null,
        ];
    }

    public function cancelSubscription(string $providerSubscriptionId): array
    {
        // DELETE de la suscripción = cancelar renovaciones; LS conserva el acceso hasta ends_at (período pagado).
        $response = $this->request('delete', '/subscriptions/'.rawurlencode($providerSubscriptionId));

        return [
            'status'  => (string) ($response['data']['attributes']['status'] ?? 'cancelled'),
            'ends_at' => isset($response['data']['attributes']['ends_at']) ? (string) $response['data']['attributes']['ends_at'] : null,
        ];
    }

    public function updateSubscriptionVariant(string $providerSubscriptionId, string $variantId, bool $invoiceImmediately, bool $disableProrations): array
    {
        $attributes = ['variant_id' => (int) $variantId];
        // Ascenso: factura el prorrateo de inmediato (el pago habilitará las capacidades). Descenso: sin crédito
        // de prorrateo. disable_prorations NO difiere el cambio: la programación del descenso la gobierna el backend.
        if ($invoiceImmediately) {
            $attributes['invoice_immediately'] = true;
        }
        if ($disableProrations) {
            $attributes['disable_prorations'] = true;
        }

        $body = [
            'data' => [
                'type'       => 'subscriptions',
                'id'         => $providerSubscriptionId,
                'attributes' => $attributes,
            ],
        ];

        $response = $this->request('patch', '/subscriptions/'.rawurlencode($providerSubscriptionId), $body);

        return ['status' => (string) ($response['data']['attributes']['status'] ?? 'active')];
    }

    public function listSubscriptionInvoices(string $providerSubscriptionId): array
    {
        // Consulta oficial PAGINADA y acotada de las facturas de la suscripción.
        $paged = $this->requestPaged('/subscription-invoices?filter[subscription_id]='.rawurlencode($providerSubscriptionId), (int) config('billing.provider_page_cap', 10));

        $invoices = [];
        foreach ($paged['rows'] as $row) {
            $attr = (array) ($row['attributes'] ?? []);
            // Campos OFICIALES de subscription-invoices (NO incluyen variant_id; la variante vive en la suscripción).
            $invoices[] = [
                'id'             => (string) ($row['id'] ?? ''),
                'status'         => (string) ($attr['status'] ?? ''),
                'total'          => (int) ($attr['total'] ?? 0),
                'currency'       => (string) ($attr['currency'] ?? 'USD'),
                'billing_reason' => (string) ($attr['billing_reason'] ?? ''),
                'created_at'     => isset($attr['created_at']) ? (string) $attr['created_at'] : null,
                'refunded'       => (bool) ($attr['refunded'] ?? false),
            ];
        }

        return $invoices;
    }

    /**
     * Recorre un listado JSON:API por páginas, ACOTADO a un máximo de páginas. Devuelve las filas acumuladas y si
     * el recorrido fue COMPLETO (`complete`): false si se alcanzó el tope con más páginas, o si una respuesta no
     * tuvo `data` de lista (inválida) — el llamador lo trata como no concluyente.
     *
     * @return array{rows: array<int, array<string, mixed>>, complete: bool}
     */
    private function requestPaged(string $path, int $maxPages, int $pageSize = 100): array
    {
        $maxPages = max(1, $maxPages);
        $separator = str_contains($path, '?') ? '&' : '?';
        $rows = [];

        for ($page = 1; $page <= $maxPages; $page++) {
            $response = $this->request('get', $path.$separator.'page[number]='.$page.'&page[size]='.$pageSize);

            if (! isset($response['data']) || ! is_array($response['data'])) {
                return ['rows' => $rows, 'complete' => false]; // respuesta inválida → no concluyente
            }
            foreach ($response['data'] as $row) {
                $rows[] = (array) $row;
            }

            $lastPage = (int) ($response['meta']['page']['lastPage'] ?? $page);
            if ($page >= $lastPage) {
                return ['rows' => $rows, 'complete' => true]; // recorrido completo
            }
        }

        return ['rows' => $rows, 'complete' => false]; // quedaban más páginas que el tope → incompleto
    }

    /**
     * Petición REST autenticada (JSON:API). Sin credenciales o ante cualquier fallo → indisponibilidad
     * sanitizada (BillingUnavailable): jamás se fabrica un resultado del proveedor.
     *
     * @param  array<string, mixed>|null  $body
     * @return array<string, mixed>
     */
    private function request(string $method, string $path, ?array $body = null): array
    {
        $apiKey = (string) config('billing.api_key');
        if ($apiKey === '') {
            throw new BillingUnavailableException();
        }

        try {
            $client = Http::withToken($apiKey)
                ->acceptJson()
                ->contentType('application/vnd.api+json')
                ->timeout((int) config('billing.http_timeout', 15));

            $url = rtrim((string) config('billing.api_base'), '/').$path;
            $response = $body === null ? $client->{$method}($url) : $client->{$method}($url, $body);
        } catch (\Throwable $e) {
            report($e);
            throw new BillingUnavailableException();
        }

        if (! $response->successful()) {
            report(new \RuntimeException('Lemon Squeezy '.strtoupper($method).' '.$path.' HTTP '.$response->status()));
            throw new BillingUnavailableException();
        }

        $json = $response->json();

        return is_array($json) ? $json : [];
    }
}
