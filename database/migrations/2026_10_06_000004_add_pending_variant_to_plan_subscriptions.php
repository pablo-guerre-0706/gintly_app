<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MOD-SUB · Variante DESTINO de un cambio de plan pendiente (aditiva, idempotente, reversible). Permite
 * correlacionar el pago EXACTO que confirma un ascenso (prorrateo) o la renovación que aplica un descenso con la
 * variante del cambio solicitado, y fijar provider_variant_id coherente con lo cobrado al confirmarse.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('plan_subscriptions')) {
            return;
        }

        Schema::table('plan_subscriptions', function (Blueprint $table): void {
            if (! Schema::hasColumn('plan_subscriptions', 'pending_variant_id')) {
                $table->string('pending_variant_id', 160)->nullable()->after('pending_period');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('plan_subscriptions')) {
            return;
        }

        Schema::table('plan_subscriptions', function (Blueprint $table): void {
            if (Schema::hasColumn('plan_subscriptions', 'pending_variant_id')) {
                $table->dropColumn('pending_variant_id');
            }
        });
    }
};
