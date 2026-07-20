<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('cash_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained('businesses')->cascadeOnDelete();
            $table->foreignId('cash_register_id')->constrained('cash_registers')->restrictOnDelete();

            // Autoría dual, quien abre puede no ser quien cierra, 2 FK a users, nombres explícitos
            $table->foreignId('opened_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('closed_by')->nullable()->constrained('users')->restrictOnDelete();

            $table->enum('status', ['abierta', 'cerrada', 'descuadrada'])->default('abierta')->index();

            $table->decimal('opening_amount', 14, 2);              // fondo inicial declarado
            $table->decimal('expected_amount', 14, 2)->nullable(); // teórico; se llena al cerrar
            $table->decimal('counted_amount', 14, 2)->nullable();  // arqueo ciego: lo que el cajero declara
            $table->json('counted_denominations')->nullable();  // evidencia del arqueo ciego (RF-06-04)

            $table->timestamp('opened_at');
            $table->timestamp('closed_at')->nullable();
            $table->string('closing_notes', 500)->nullable();      // obligatoria si hay dif.
            $table->timestamps();
        });

        // Fondo inicial nunca negativo
        DB::statement('ALTER TABLE cash_sessions ADD CONSTRAINT chk_cash_session_opening
            CHECK (opening_amount >= 0)');
        // UNA sola sesión 'abierta' por caja — garantizada por el MOTOR, no por la aplicación.
        // MySQL 8 no tiene índices parciales (el "WHERE status='abierta'" de PostgreSQL). Se emula
        // con una columna generada VIRTUAL que vale cash_register_id solo mientras la sesión está
        // abierta, y NULL en cualquier otro estado. Sobre ella, un UNIQUE:
        //   · sesiones cerradas/descuadradas → open_register_lock = NULL → los NULL no colisionan → ilimitadas
        //   · dos 'abierta' para la misma caja → mismo valor no-nulo → choque de UNIQUE → rechazo
        // Al cambiar status a 'cerrada' la columna se recalcula a NULL y libera el candado sola.
        DB::statement("
            ALTER TABLE cash_sessions
                ADD COLUMN open_register_lock BIGINT UNSIGNED
                    GENERATED ALWAYS AS (CASE WHEN status = 'abierta' THEN cash_register_id END) VIRTUAL,
                ADD UNIQUE KEY uniq_open_session_per_register (open_register_lock)
        ");
        DB::statement("ALTER TABLE cash_sessions
            ADD COLUMN open_user_lock BIGINT UNSIGNED
                GENERATED ALWAYS AS (CASE WHEN status = 'abierta' THEN opened_by END) VIRTUAL,
            ADD UNIQUE KEY uniq_open_session_per_user (open_user_lock)");
        DB::statement("ALTER TABLE cash_sessions
            ADD COLUMN difference DECIMAL(14,2)
            GENERATED ALWAYS AS (counted_amount - expected_amount) STORED");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cash_sessions');
    }
};
