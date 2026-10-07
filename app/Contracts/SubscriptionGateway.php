<?php

declare(strict_types=1);

namespace App\Contracts;

/**
 * Puerto del proveedor de suscripción (Lemon Squeezy). UNA sola implementación real + un doble de prueba;
 * NO es una arquitectura multiproveedor. Mantiene las llamadas de red FUERA de transacciones MySQL.
 */
interface SubscriptionGateway
{
    /**
     * Crea un checkout ALOJADO restringido a la variante (cantidad 1, sin descuentos, sin trial, sin que el
     * comprador altere la contratación). Devuelve ['id' => string, 'url' => string]. Lanza en fallo/indisponibilidad.
     *
     * @param  array{variant_id:string, store_id:string, mode:string, email:?string, business_id:int, intent_key:string, return_url:?string, expires_at:?string}  $params
     * @return array{id:string, url:string}
     */
    public function createCheckout(array $params): array;

    /** Verifica X-Signature (HMAC-SHA256 del cuerpo ORIGINAL) con comparación segura. */
    public function verifySignature(string $rawPayload, ?string $signature): bool;

    /**
     * Resuelve un resultado INCIERTO: consulta OFICIAL y PAGINADA (acotada) de los checkouts de la tienda para
     * saber si el proveedor creó realmente el checkout de este intent_key (en checkout_data.custom). Devuelve un
     * sobre:
     *   outcome='found'      → 'checkout' trae id,url,expires_at,store_id,test_mode,variant_id,business_id,intent_key
     *                          (el llamador verifica tienda/modo/negocio/variante).
     *   outcome='absent'     → búsqueda COMPLETA y acotada: el proveedor NO lo tiene.
     *   outcome='incomplete' → no concluyente (más páginas que el tope, o respuesta inválida): NO decide.
     * Lanza BillingUnavailable si no se puede consultar. El llamador NUNCA recrea a ciegas con 'incomplete'.
     *
     * @return array{outcome:string, checkout: array<string, mixed>|null}
     */
    public function findCheckoutByIntentKey(string $intentKey): array;

    /**
     * Recurso OFICIAL de la suscripción del proveedor: su variante ACTUAL (attributes.variant_id), estado y
     * renews_at. Se usa para correlacionar que el proveedor APLICÓ el cambio de variante (las subscription-invoices
     * no traen variante). Lanza en indisponibilidad.
     *
     * @return array{variant_id:string, status:string, renews_at:?string}
     */
    public function getSubscription(string $providerSubscriptionId): array;

    /**
     * Cancela las renovaciones de la MISMA suscripción del proveedor (no borra ni revoca el período ya pagado).
     * Devuelve el estado remoto resultante. Lanza en indisponibilidad (no se simula un resultado).
     *
     * @return array{status:string, ends_at:?string}
     */
    public function cancelSubscription(string $providerSubscriptionId): array;

    /**
     * Cambia la VARIANTE de la MISMA suscripción del proveedor (conserva business/suscripción). El ascenso
     * factura de inmediato (invoiceImmediately=true, prorrateo cobrado → evidencia de pago que habilita las
     * nuevas capacidades); el descenso se aplica sin crédito de prorrateo (disableProrations=true) en el límite
     * del período. `disableProrations` NO es una programación futura: solo suprime el ajuste de prorrateo.
     *
     * @return array{status:string}
     */
    public function updateSubscriptionVariant(string $providerSubscriptionId, string $variantId, bool $invoiceImmediately, bool $disableProrations): array;

    /**
     * Consulta OFICIAL de las facturas de una suscripción (reconciliación: recuperar pagos cuyo webhook se
     * perdió). Normaliza lo imprescindible; nunca incluye datos de tarjeta ni PII.
     *
     * @return array<int, array{id:string, status:string, total:int, currency:string, billing_reason:string, created_at:?string, refunded:bool}>
     */
    public function listSubscriptionInvoices(string $providerSubscriptionId): array;
}
