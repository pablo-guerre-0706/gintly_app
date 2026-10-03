<?php

declare(strict_types=1);

use App\Enums\RoleName;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Reconciliación de seguridad (RF-01) · Invalida sesiones PERSISTIDAS de cuentas ROL-SYS.
 *
 * Bloquear nuevos logins y neutralizar la cuenta NO invalida una sesión ya emitida: con el driver
 * de sesión = database, auth:sanctum seguiría resolviendo al usuario mientras exista su fila en
 * `sessions`. Esta migración elimina esas filas para las cuentas ROL-SYS (defensa en profundidad;
 * la garantía por-petición la da el middleware EnsureOperableUser). Idempotente y no destructiva
 * para el resto de datos: solo borra filas de sesión de cuentas de sistema.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('sessions') || ! Schema::hasColumn('sessions', 'user_id')) {
            return; // Driver de sesión no persistido en BD: nada que invalidar.
        }

        $systemUserIds = DB::table('model_has_roles as mhr')
            ->join('roles as r', 'r.id', '=', 'mhr.role_id')
            ->where('r.name', RoleName::System->value)
            ->where('mhr.model_type', 'App\\Models\\User')
            ->pluck('mhr.model_id')
            ->unique()
            ->all();

        if ($systemUserIds !== []) {
            DB::table('sessions')->whereIn('user_id', $systemUserIds)->delete();
        }
    }

    public function down(): void
    {
        // Forward-only: una sesión eliminada no se restablece.
    }
};
