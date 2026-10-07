<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Contracts\SubscriptionGateway;
use App\Http\Controllers\Controller;
use App\Services\Billing\SubscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Recepción de webhooks del proveedor (público; CSRF excluido SOLO aquí). Verifica X-Signature (HMAC-SHA256
 * sobre el cuerpo ORIGINAL) antes de procesar; firma inválida → 400 sin efecto. El procesamiento registra el
 * evento de forma durable y solo responde 200 cuando quedó procesado o reconocido; si el procesamiento falla,
 * propaga (no 200) para que el proveedor reintente.
 */
final class BillingWebhookController extends Controller
{
    public function __construct(
        private readonly SubscriptionGateway $gateway,
        private readonly SubscriptionService $subscriptions,
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $raw = $request->getContent();
        $signature = $request->header('X-Signature');

        if (! $this->gateway->verifySignature($raw, is_string($signature) ? $signature : null)) {
            return response()->json(['message' => 'Firma no válida.'], 400);
        }

        $payload = json_decode($raw, true);
        if (! is_array($payload)) {
            return response()->json(['message' => 'Cuerpo no válido.'], 400);
        }

        $result = $this->subscriptions->processVerifiedWebhook($payload);

        return response()->json(['status' => $result], 200);
    }
}
