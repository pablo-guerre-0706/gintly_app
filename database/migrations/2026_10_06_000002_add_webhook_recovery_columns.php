<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MOD-SUB · Recuperabilidad de webhooks (aditiva, idempotente, reversible). Un evento PROPIO que llega fuera
 * de orden (p. ej. el pago antes de que su suscripción esté correlacionada localmente) NO puede perderse tras
 * responder 200 al proveedor. Para ello el evento se "aparca": se guarda su cuerpo original y la marca
 * parked_at, dejando processed_at NULL, de modo que la reconciliación programada lo reintente.
 *
 *   - payload   · cuerpo ORIGINAL del evento (para reproducirlo sin volver a pedirlo al proveedor).
 *   - parked_at · marca de "recibido pero pendiente de correlación" (recuperable; distinto de ajeno/definitivo).
 *   - attempts  · reintentos de reconciliación (para abandonar tras un máximo sin correlación posible).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('billing_webhook_events')) {
            return;
        }

        Schema::table('billing_webhook_events', function (Blueprint $table): void {
            if (! Schema::hasColumn('billing_webhook_events', 'payload')) {
                $table->longText('payload')->nullable()->after('event_name');
            }
            if (! Schema::hasColumn('billing_webhook_events', 'parked_at')) {
                $table->timestamp('parked_at')->nullable()->after('received_at');
            }
            if (! Schema::hasColumn('billing_webhook_events', 'attempts')) {
                $table->unsignedInteger('attempts')->default(0)->after('parked_at');
            }
        });

        // Índice para el barrido de reconciliación (eventos aparcados y aún no procesados).
        if (! $this->indexExists('billing_webhook_events', 'idx_webhook_event_parked')) {
            Schema::table('billing_webhook_events', function (Blueprint $table): void {
                $table->index(['parked_at', 'processed_at'], 'idx_webhook_event_parked');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('billing_webhook_events')) {
            return;
        }

        if ($this->indexExists('billing_webhook_events', 'idx_webhook_event_parked')) {
            Schema::table('billing_webhook_events', function (Blueprint $table): void {
                $table->dropIndex('idx_webhook_event_parked');
            });
        }

        Schema::table('billing_webhook_events', function (Blueprint $table): void {
            foreach (['attempts', 'parked_at', 'payload'] as $column) {
                if (Schema::hasColumn('billing_webhook_events', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }

    private function indexExists(string $table, string $index): bool
    {
        $connection = Schema::getConnection();
        $database = $connection->getDatabaseName();

        return (int) $connection->selectOne(
            'SELECT COUNT(1) AS c FROM information_schema.statistics WHERE table_schema = ? AND table_name = ? AND index_name = ?',
            [$database, $table, $index]
        )->c > 0;
    }
};
