<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MOD-SUB · Vencimiento FINITO del checkout (aditiva, idempotente, reversible). Cada intento de contratación
 * lleva un expires_at finito: una URL vencida deja de ser utilizable y no bloquea un intento nuevo. Junto con
 * la regla "un solo checkout abierto por negocio", evita que queden DOS contrataciones utilizables a la vez.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('checkout_intents')) {
            return;
        }

        Schema::table('checkout_intents', function (Blueprint $table): void {
            if (! Schema::hasColumn('checkout_intents', 'expires_at')) {
                $table->timestamp('expires_at')->nullable()->after('checkout_url');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('checkout_intents')) {
            return;
        }

        Schema::table('checkout_intents', function (Blueprint $table): void {
            if (Schema::hasColumn('checkout_intents', 'expires_at')) {
                $table->dropColumn('expires_at');
            }
        });
    }
};
