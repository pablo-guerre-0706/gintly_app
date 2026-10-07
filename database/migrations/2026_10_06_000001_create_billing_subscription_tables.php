<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Subsistema de SUSCRIPCIÓN SaaS de Gintly (cobro de Gintly al negocio). ESTRICTAMENTE separado de
 * invoice_payments / receivable_payments del ERP. Progresiva, aditiva e idempotente; down() revierte.
 *
 *  - plan_subscriptions      · estado comercial VIGENTE del negocio (uno por negocio; historial en las demás).
 *  - checkout_intents        · correlación de intentos de contratación (idempotencia propia).
 *  - billing_webhook_events  · identidad persistente de eventos del proveedor (deduplicación).
 *  - subscription_payments   · evidencia normalizada de pagos que cubren períodos concretos (sin tarjetas/PII).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('plan_subscriptions')) {
            Schema::create('plan_subscriptions', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->string('plan_key', 20);
                $table->string('period', 10);                 // monthly | annual
                $table->string('status', 20);                 // pending_payment|incomplete|active|past_due|canceled|expired
                $table->string('provider', 30)->default('lemon_squeezy');
                $table->string('provider_mode', 10);          // test | live
                $table->string('store_id', 60)->nullable();
                $table->string('provider_subscription_id', 160)->nullable();
                $table->string('provider_variant_id', 160)->nullable();
                $table->timestamp('current_period_start')->nullable();
                $table->timestamp('paid_until')->nullable();   // VIGENCIA efectivamente pagada
                $table->timestamp('renews_at')->nullable();    // informativo (no demuestra pago)
                $table->timestamp('canceled_at')->nullable();
                // Cambio de plan PROGRAMADO (downgrade al fin del período pagado).
                $table->string('pending_plan_key', 20)->nullable();
                $table->string('pending_period', 10)->nullable();
                $table->timestamp('pending_effective_at')->nullable();
                $table->timestamps();

                $table->unique('business_id', 'uniq_plan_subscription_business'); // una suscripción comercial por negocio
                $table->index(['status', 'paid_until'], 'idx_plan_subscription_status');
                $table->index(['provider', 'provider_mode'], 'idx_plan_subscription_provider_mode');
            });
        }

        if (! Schema::hasTable('checkout_intents')) {
            Schema::create('checkout_intents', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->char('idempotency_key', 36);
                $table->string('plan_key', 20);
                $table->string('period', 10);
                $table->string('provider', 30)->default('lemon_squeezy');
                $table->string('provider_mode', 10);
                $table->string('store_id', 60)->nullable();
                $table->string('provider_variant_id', 160)->nullable();
                $table->string('status', 20)->default('pending'); // pending|created|completed|failed
                $table->string('provider_checkout_id', 190)->nullable();
                $table->text('checkout_url')->nullable();
                $table->string('fingerprint', 64)->nullable();     // plan+period normalizados (anti-doble envío)
                $table->timestamps();

                $table->unique('idempotency_key', 'uniq_checkout_intent_key'); // árbitro de idempotencia del checkout
                $table->index(['business_id', 'status'], 'idx_checkout_intent_business');
                $table->index('provider_checkout_id', 'idx_checkout_intent_provider');
            });
        }

        if (! Schema::hasTable('billing_webhook_events')) {
            Schema::create('billing_webhook_events', function (Blueprint $table): void {
                $table->id();
                $table->string('provider', 30)->default('lemon_squeezy');
                $table->string('provider_mode', 10);
                $table->string('event_identity', 190);  // identidad persistente (distingue versiones/reenvíos)
                $table->string('event_name', 80)->nullable();
                $table->foreignId('business_id')->nullable()->constrained('businesses')->nullOnDelete();
                $table->timestamp('received_at');
                $table->timestamp('processed_at')->nullable(); // NULL ⇒ recibido pero no procesado (recuperable)
                $table->timestamps();

                // Dedup definitivo por (proveedor, modo, identidad del evento).
                $table->unique(['provider', 'provider_mode', 'event_identity'], 'uniq_webhook_event_identity');
            });
        }

        if (! Schema::hasTable('subscription_payments')) {
            Schema::create('subscription_payments', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
                $table->foreignId('plan_subscription_id')->constrained('plan_subscriptions')->cascadeOnDelete();
                $table->string('provider', 30)->default('lemon_squeezy');
                $table->string('provider_mode', 10);
                $table->string('provider_payment_id', 190);   // order/invoice id del proveedor
                $table->string('event_identity', 190)->nullable();
                $table->string('status', 20);                  // paid | refunded | partial_refund
                $table->bigInteger('amount_minor');            // importe COBRADO (unidades menores de su moneda)
                $table->string('currency', 3);                 // moneda COBRADA (p. ej. USD)
                $table->bigInteger('catalog_amount_minor')->nullable(); // importe ANUNCIADO (centavos NIO)
                $table->string('catalog_currency', 3)->default('NIO');
                $table->timestamp('period_start')->nullable();
                $table->timestamp('period_end')->nullable();
                $table->timestamps();

                // Anti doble conteo del mismo pago del proveedor.
                $table->unique(['provider', 'provider_mode', 'provider_payment_id'], 'uniq_subscription_payment');
                $table->index('business_id', 'idx_subscription_payment_business');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_payments');
        Schema::dropIfExists('billing_webhook_events');
        Schema::dropIfExists('checkout_intents');
        Schema::dropIfExists('plan_subscriptions');
    }
};
