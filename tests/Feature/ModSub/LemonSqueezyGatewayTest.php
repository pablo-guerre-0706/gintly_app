<?php

declare(strict_types=1);

namespace Tests\Feature\ModSub;

use App\Exceptions\BillingUnavailableException;
use App\Exceptions\CheckoutResultUnknownException;
use App\Services\Billing\BillingMode;
use App\Services\Billing\LemonSqueezyGateway;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * MOD-SUB · Transporte REAL de LemonSqueezyGateway sustituyendo SOLO el cliente HTTP (Http::fake). Verifica el
 * cuerpo JSON:API del checkout (variante, tienda, modo, URL de retorno controlada, variante habilitada, SIN
 * trial), la cancelación, el cambio de variante (ascenso/descenso) y la consulta de facturas, además de la firma
 * HMAC sobre los BYTES originales (no la del doble de prueba). No requiere base de datos.
 */
final class LemonSqueezyGatewayTest extends TestCase
{
    private function configure(string $purpose = 'demo', string $mode = 'test'): void
    {
        config([
            'billing.deployment_purpose' => $purpose,
            'billing.provider_mode'      => $mode,
            'billing.api_key'            => 'lsk_test_ABC',
            'billing.api_base'           => 'https://api.lemonsqueezy.com/v1',
            'billing.store_id'           => 'store_9',
            'billing.webhook_secret'     => 'whsec_test_123',
            'billing.http_timeout'       => 10,
            'billing.return_url'         => 'https://app.gintly.test/billing/return',
        ]);
    }

    private function gateway(): LemonSqueezyGateway
    {
        return new LemonSqueezyGateway(new BillingMode());
    }

    public function test_variante_inexistente_se_sanitiza_sin_repetir_el_post(): void
    {
        $this->configure();
        Http::fake([
            'api.lemonsqueezy.com/v1/checkouts' => Http::response([
                'errors' => [['title' => 'Not Found', 'detail' => 'provider-private-details']],
            ], 404),
        ]);

        try {
            $this->gateway()->createCheckout([
                'variant_id' => '555', 'store_id' => 'store_9', 'mode' => 'test',
                'email' => 'owner@negocio.test', 'business_id' => 42, 'intent_key' => 'key-404',
            ]);
            $this->fail('Un HTTP 404 del proveedor no debe producir un checkout exitoso.');
        } catch (BillingUnavailableException $error) {
            $response = $error->render();
            $this->assertSame(503, $response->getStatusCode());
            $this->assertSame('BILLING_UNAVAILABLE', $response->getData(true)['code']);
            $this->assertStringNotContainsString('provider-private-details', $response->getContent());
        }

        Http::assertSentCount(1);
    }

    public function test_resultado_incierto_indica_conservar_el_mismo_intento(): void
    {
        $response = (new CheckoutResultUnknownException())->render();
        $this->assertSame(409, $response->getStatusCode());
        $this->assertSame('CHECKOUT_RESULT_UNKNOWN', $response->getData(true)['code']);
        $this->assertStringContainsString('misma solicitud y selección', $response->getData(true)['message']);
        $this->assertStringNotContainsString('nueva solicitud', $response->getData(true)['message']);
    }

    public function test_checkout_envia_jsonapi_sin_trial_restringido_a_la_variante(): void
    {
        $this->configure('demo', 'test');
        Http::fake([
            'api.lemonsqueezy.com/v1/checkouts' => Http::response(['data' => ['id' => 'co_1', 'attributes' => ['url' => 'https://pay.test/co_1']]], 201),
        ]);

        $result = $this->gateway()->createCheckout([
            'variant_id' => '555', 'store_id' => 'store_9', 'mode' => 'test',
            'email' => 'owner@negocio.test', 'business_id' => 42, 'intent_key' => 'key-123',
            'return_url' => 'https://app.gintly.test/billing/return',
        ]);

        $this->assertSame(['id' => 'co_1', 'url' => 'https://pay.test/co_1'], $result);

        Http::assertSent(function ($request) {
            $body = $request->data();

            $this->assertSame('POST', $request->method());
            $this->assertSame('Bearer lsk_test_ABC', $request->header('Authorization')[0] ?? null);
            $this->assertStringContainsString('application/vnd.api+json', $request->header('Content-Type')[0] ?? '');

            $this->assertSame('checkouts', $body['data']['type']);
            $this->assertTrue($body['data']['attributes']['test_mode']);
            $this->assertSame([555], $body['data']['attributes']['product_options']['enabled_variants']);
            $this->assertSame('https://app.gintly.test/billing/return', $body['data']['attributes']['product_options']['redirect_url']);
            $this->assertSame('42', $body['data']['attributes']['checkout_data']['custom']['business_id']);
            $this->assertSame('key-123', $body['data']['attributes']['checkout_data']['custom']['intent_key']);
            $this->assertSame('store_9', $body['data']['relationships']['store']['data']['id']);
            $this->assertSame('555', $body['data']['relationships']['variant']['data']['id']);

            // Prueba gratuita OMITIDA de forma explícita con la opción OFICIAL (no por ausencia de la palabra):
            // checkout_options.skip_trial=true; y sin campo de descuento.
            $this->assertTrue($body['data']['attributes']['checkout_options']['skip_trial']);
            $this->assertFalse($body['data']['attributes']['checkout_options']['discount']);

            return true;
        });
    }

    public function test_checkout_en_modo_live_marca_test_mode_falso(): void
    {
        $this->configure('commercial', 'live');
        Http::fake(['*' => Http::response(['data' => ['id' => 'co_live', 'attributes' => ['url' => 'https://pay.live/co']]], 201)]);

        $this->gateway()->createCheckout([
            'variant_id' => '1', 'store_id' => 'store_9', 'mode' => 'live', 'email' => null,
            'business_id' => 1, 'intent_key' => 'k', 'return_url' => null,
        ]);

        Http::assertSent(fn ($request) => $request['data']['attributes']['test_mode'] === false);
    }

    public function test_checkout_sin_credenciales_es_indisponible(): void
    {
        $this->configure('demo', 'test');
        config(['billing.api_key' => '']);
        Http::fake();

        $this->expectException(BillingUnavailableException::class);
        $this->gateway()->createCheckout([
            'variant_id' => '1', 'store_id' => 'store_9', 'mode' => 'test', 'email' => null,
            'business_id' => 1, 'intent_key' => 'k', 'return_url' => null,
        ]);
        Http::assertNothingSent();
    }

    public function test_checkout_http_no_exitoso_es_indisponible(): void
    {
        $this->configure('demo', 'test');
        Http::fake(['*' => Http::response(['errors' => [['detail' => 'bad']]], 422)]);

        $this->expectException(BillingUnavailableException::class);
        $this->gateway()->createCheckout([
            'variant_id' => '1', 'store_id' => 'store_9', 'mode' => 'test', 'email' => null,
            'business_id' => 1, 'intent_key' => 'k', 'return_url' => null,
        ]);
    }

    public function test_cancelacion_envia_delete(): void
    {
        $this->configure('demo', 'test');
        Http::fake(['*' => Http::response(['data' => ['attributes' => ['status' => 'cancelled', 'ends_at' => '2026-12-31T00:00:00Z']]], 200)]);

        $state = $this->gateway()->cancelSubscription('sub_77');

        $this->assertSame('cancelled', $state['status']);
        $this->assertSame('2026-12-31T00:00:00Z', $state['ends_at']);
        Http::assertSent(fn ($request) => $request->method() === 'DELETE'
            && str_ends_with($request->url(), '/subscriptions/sub_77'));
    }

    public function test_ascenso_factura_de_inmediato_y_descenso_suprime_prorrateo(): void
    {
        $this->configure('demo', 'test');
        Http::fake(['*' => Http::response(['data' => ['attributes' => ['status' => 'active']]], 200)]);

        $this->gateway()->updateSubscriptionVariant('sub_1', '777', true, false);
        Http::assertSent(function ($request) {
            if ($request->method() !== 'PATCH') {
                return false;
            }
            $body = $request->data();

            return $body['data']['attributes']['variant_id'] === 777
                && ($body['data']['attributes']['invoice_immediately'] ?? false) === true
                && ! array_key_exists('disable_prorations', $body['data']['attributes']);
        });

        $this->gateway()->updateSubscriptionVariant('sub_1', '111', false, true);
        Http::assertSent(function ($request) {
            if ($request->method() !== 'PATCH') {
                return false;
            }
            $body = $request->data();

            return ($body['data']['attributes']['disable_prorations'] ?? false) === true
                && ! array_key_exists('invoice_immediately', $body['data']['attributes']);
        });
    }

    public function test_busca_checkout_por_intent_key_paginando_hasta_la_segunda_pagina(): void
    {
        $this->configure('demo', 'test');
        // Página 1 (sin el buscado, lastPage=2) y página 2 (con el buscado): la búsqueda debe recorrer AMBAS.
        Http::fake(function ($request) {
            if (str_contains(urldecode($request->url()), 'page[number]=1')) {
                return Http::response(['data' => [
                    ['id' => 'co_8', 'attributes' => ['url' => 'https://pay.test/co_8', 'store_id' => 'store_9', 'test_mode' => true, 'checkout_data' => ['custom' => ['intent_key' => 'otra', 'business_id' => '9']]]],
                ], 'meta' => ['page' => ['currentPage' => 1, 'lastPage' => 2]]], 200);
            }

            return Http::response(['data' => [
                ['id' => 'co_7', 'attributes' => ['url' => 'https://pay.test/co_7', 'expires_at' => '2026-12-31T00:00:00Z', 'store_id' => 'store_9', 'test_mode' => true, 'checkout_data' => ['custom' => ['intent_key' => 'k-9', 'business_id' => '5']]], 'relationships' => ['variant' => ['data' => ['id' => '44']]]],
            ], 'meta' => ['page' => ['currentPage' => 2, 'lastPage' => 2]]], 200);
        });

        $r = $this->gateway()->findCheckoutByIntentKey('k-9'); // en la SEGUNDA página
        $this->assertSame('found', $r['outcome']);
        $this->assertSame('co_7', $r['checkout']['id']);
        $this->assertSame('https://pay.test/co_7', $r['checkout']['url']);
        $this->assertSame('2026-12-31T00:00:00Z', $r['checkout']['expires_at']);
        $this->assertSame('5', $r['checkout']['business_id']);
        $this->assertSame('44', $r['checkout']['variant_id']);

        // Ausente tras recorrer TODAS las páginas → 'absent' (concluyente).
        $this->assertSame('absent', $this->gateway()->findCheckoutByIntentKey('inexistente')['outcome']);

        Http::assertSent(fn ($request) => str_contains(urldecode($request->url()), 'page[number]=2')
            && str_contains(urldecode($request->url()), 'filter[store_id]=store_9'));
    }

    public function test_busqueda_incompleta_cuando_excede_el_tope_de_paginas(): void
    {
        $this->configure('demo', 'test');
        config(['billing.provider_page_cap' => 1]); // tope de 1 página
        Http::fake(['*' => Http::response(['data' => [], 'meta' => ['page' => ['currentPage' => 1, 'lastPage' => 5]]], 200)]);

        // Hay más páginas que el tope sin hallar: NO concluyente → 'incomplete' (el servicio bloquea, no recrea).
        $this->assertSame('incomplete', $this->gateway()->findCheckoutByIntentKey('k-9')['outcome']);
    }

    public function test_lista_de_facturas_se_normaliza(): void
    {
        $this->configure('demo', 'test');
        Http::fake(['*' => Http::response(['data' => [
            ['id' => 'inv_1', 'attributes' => ['status' => 'paid', 'total' => 12000, 'currency' => 'USD', 'billing_reason' => 'renewal', 'created_at' => '2026-01-01T00:00:00Z', 'refunded' => false]],
            ['id' => 'inv_2', 'attributes' => ['status' => 'pending', 'total' => 0, 'currency' => 'USD', 'billing_reason' => 'initial']],
        ]], 200)]);

        $invoices = $this->gateway()->listSubscriptionInvoices('sub_5');

        $this->assertCount(2, $invoices);
        $this->assertSame('inv_1', $invoices[0]['id']);
        $this->assertSame('paid', $invoices[0]['status']);
        $this->assertSame(12000, $invoices[0]['total']);
        $this->assertSame('renewal', $invoices[0]['billing_reason']);
        $this->assertFalse($invoices[1]['refunded']);
        Http::assertSent(fn ($request) => $request->method() === 'GET'
            && str_contains(urldecode($request->url()), 'filter[subscription_id]=sub_5'));
    }

    public function test_consulta_oficial_de_la_suscripcion_expone_su_variante_actual(): void
    {
        $this->configure('demo', 'test');
        Http::fake(['*' => Http::response(['data' => ['id' => 'sub_5', 'attributes' => ['status' => 'active', 'variant_id' => 777, 'renews_at' => '2026-12-31T00:00:00Z']]], 200)]);

        $s = $this->gateway()->getSubscription('sub_5');
        $this->assertSame('777', $s['variant_id']); // variante ACTUAL vive en la suscripción, no en las facturas
        $this->assertSame('active', $s['status']);
        $this->assertSame('2026-12-31T00:00:00Z', $s['renews_at']);
        Http::assertSent(fn ($request) => $request->method() === 'GET' && str_ends_with($request->url(), '/subscriptions/sub_5'));
    }

    public function test_firma_hmac_sobre_los_bytes_originales(): void
    {
        $this->configure('demo', 'test');
        $gateway = $this->gateway();

        $raw = '{"meta":{"event_name":"subscription_payment_success"},"data":{"id":"inv_9"}}';
        $good = hash_hmac('sha256', $raw, 'whsec_test_123');

        $this->assertTrue($gateway->verifySignature($raw, $good));
        // Cuerpo alterado en un byte → firma inválida.
        $this->assertFalse($gateway->verifySignature($raw.' ', $good));
        $this->assertFalse($gateway->verifySignature($raw, strrev($good)));
        $this->assertFalse($gateway->verifySignature($raw, null));
        $this->assertFalse($gateway->verifySignature($raw, ''));

        // Sin secreto configurado jamás valida.
        config(['billing.webhook_secret' => '']);
        $this->assertFalse($gateway->verifySignature($raw, $good));
    }
}
